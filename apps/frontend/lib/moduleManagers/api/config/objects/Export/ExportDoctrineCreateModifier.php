<?php

namespace Api\Config\Objects\Export;

use AbilitiesManager;
use Api\DataTypes\ConversionContext;
use Api\Exceptions\ApiException;
use Api\Export\ExportInput;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Actions\ActionInputBuilder as ExportProcedureInputBuilder;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use ApiScalingExportJobHandler;
use Exception;
use ExportSettingsManager;
use RESTClient;
use stringTools;

class ExportDoctrineCreateModifier implements DoctrineCreateModifier
{
	private ExportSaveManager $exportSaveManager;
	private ExportInput $exportInput;

	/**
	 * @param DoctrineCreateContext $createContext
	 * @return array
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$isInternalRequest = $createContext->getApiActions()->isInternalRequest();
		$version = $createContext->getVersion();

		$conversionContext = ConversionContext::createFromApiActions($createContext->getApiActions());
		$apiUser = $createContext->getApiActions()->apiUser;

		$this->exportSaveManager = new ExportSaveManager(
			SaveManagerContext::fromApiActions($createContext->getApiActions()),
			AbilitiesManager::getInstance()
		);

		$this->validateCreate($version, $isInternalRequest, $createContext, $conversionContext);

		$export = $this->exportSaveManager->doCreate(
			$createContext->getAccountId(),
			$apiUser->id,
			$apiUser->getRole(),
			$this->exportInput,
		);

		return [SystemFieldNames::ID => $export->id];
	}

	/**
	 * @param $version
	 * @param $isInternalRequest
	 * @param $createContext
	 * @param $conversionContext
	 * @return void
	 * @throws Exception
	 */
	private function validateCreate(int $version, bool $isInternalRequest, DoctrineCreateContext $createContext, ConversionContext $conversionContext): void
	{
		// The fields property is required in V5.
		$fieldNames = $createContext->getRepresentation()->getFields();
		if (is_null($fieldNames)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				"fields",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		if (empty($fieldNames)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_FIELDS_EMPTY,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$procedure = $createContext->getRepresentation()->getProcedure();
		if (!$procedure) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				"procedure",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$procedureParts = preg_split('/\//', $procedure->getName());
		$objectName = $procedureParts[0];
		$procedureName = $procedureParts[1];

		$inputArguments = $procedure->getArguments();
		if (empty($inputArguments)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'Arguments are missing or empty',
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$objectDefinition = ObjectDefinitionCatalog::getInstance()
			->findObjectDefinitionByObjectType($version, $createContext->getAccountId(), $objectName);
		if (!$objectDefinition) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// Verify Export Procedure
		$procedureDefinition = $this->exportSaveManager->verifyExportProcedure($objectName, $procedureName, $isInternalRequest, $conversionContext);

		$validArgs = $this->exportSaveManager->getExportManager()
			->validateV5Arguments($procedureDefinition, $inputArguments);

		$procedureInputBuilder = ExportProcedureInputBuilder::create()
			->withActionDefinition($procedureDefinition)
			->withArguments($validArgs);

		// Add object name to metrics object at earliest possible point so that it is guaranteed to appear in the
		// ApiMetrics log even if API exception is thrown
		$metrics = $createContext->getApiActions()->getMetrics();
		$validObjectName = $objectDefinition->getType();
		$this->exportSaveManager->addCollectedObjectNameToExportMetricsObject(
			$metrics,
			$validObjectName
		);

		// optional export parameters
		$includeByteOrderMark = $createContext->getRepresentation()->getIncludeByteOrderMark() ?? false;
		$maxFileSizeBytes = $this->validateMaxFileSize($createContext);

		$this->exportInput = $this->exportSaveManager->validateCreate(
			$objectDefinition,
			$fieldNames,
			$procedureInputBuilder,
			$includeByteOrderMark,
			$maxFileSizeBytes
		);
	}

	/**
	 * @param $createContext
	 * @return int
	 */
	private function validateMaxFileSize($createContext): int
	{
		$maxFileSizeCap = (int)ExportSettingsManager::getInstance($createContext->getAccountId())->getSetting(
			ExportSettingsManager::EXPORT_MAX_FILE_SIZE_IN_BYTES,
			ApiScalingExportJobHandler::MAX_FILE_SIZE_IN_BYTES_DEFAULT
		);

		// maxFileSizeBytes not set by user, return account default
		if ($createContext->getRepresentation()->getIsMaxFileSizeBytesSet() == false) {
			return $maxFileSizeCap;
		}

		$maxFileSizeBytes = $createContext->getRepresentation()->getMaxFileSizeBytes();
		if ($maxFileSizeBytes < ApiScalingExportJobHandler::MIN_FILE_SIZE_IN_BYTES_DEFAULT) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_FILE_SIZE_BELOW_MINIMUM_DEFAULT_THRESHOLD,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		if ($maxFileSizeBytes > $maxFileSizeCap) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_FILE_SIZE_ABOVE_MAXIMUM_THRESHOLD,
				$maxFileSizeCap . ' bytes',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $maxFileSizeBytes;
	}
}

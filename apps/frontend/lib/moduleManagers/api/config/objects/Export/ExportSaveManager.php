<?php

namespace Api\Config\Objects\Export;

use Abilities;
use AbilitiesManager;
use AccountSettingsManager;
use Api\DataTypes\ArrayDataType;
use Api\DataTypes\MapDataType;
use Api\Export\ExportInput;
use Api\Objects\ObjectOperationDefinition;
use Api\Objects\StaticObjectDefinition;
use Api\Objects\StaticObjectDefinitionCatalog;
use ApiErrorLibrary;
use Api\Actions\ActionInputBuilder as ExportProcedureInputBuilder;
use Api\DataTypes\ConversionContext;
use Api\Exceptions\ApiException;
use Api\Export\Exceptions\ExportInvalidProcedureArgumentException;
use Api\Export\Exceptions\ExportMissingRequiredProcedureArgumentsException;
use Api\Export\Exceptions\ExportUnknownProcedureArgumentsException;
use Api\Export\ExportInputBuilder;
use Api\Export\ExportManager;
use Api\Export\ExportMetricsHelper;
use Api\Export\ProcedureDefinition;
use Api\Export\ProcedureDefinitionCatalog;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use apiTools;
use Doctrine_Query_Exception;
use Doctrine_Record_Exception;
use Exception;
use GraphiteClient;
use PardotLogger;
use Pardot\Constants\ShardDb\Export\RequestTypeConstants as ExportRequestTypeConstants;
use Pardot\Constants\ShardDb\Export\StatusConstants as ExportStatusConstants;
use RESTClient;
use sfResponse;
use ShardManager;
use piBackgroundQueueTable;
use piExport;
use piExportFile;
use piExportFileTable;
use piExportTable;
use piUser;
use piUserTable;
use stringTools;

class ExportSaveManager
{
	public const QUERY_LIMIT = 1000;

	/**
	 * [ object name => [procedure names], ...]
	 * @var array
	 */
	public const DISALLOW_LIST_FOR_EXTERNAL_REQUESTS = [
		"LifecycleStageProspect" => [
			"filter_by_prospect_updated_at",
			"filter_by_updated_at",
		],
		"TaggedObject" => [
			"filter_by_prospect_updated_at",
			"filter_by_updated_at",
		],
	];

	private ProcedureDefinitionCatalog $procedureDefinitionCatalog;
	private AbilitiesManager $abilitiesManager;
	private ExportMetricsHelper $exportMetricsHelper;
	protected ?ExportManager $exportManager = null;
	private ShardManager $shardManager;
	protected ?piExportTable $piExportTable = null;
	protected ?piExportFileTable $piExportFileTable = null;
	private SaveManagerContext $context;
	protected ?StaticObjectDefinitionCatalog $objectDefinitionCatalog = null;

	/**
	 * @param SaveManagerContext $saveManagerContext
	 * @param AbilitiesManager|null $abilitiesManager
	 * @param ExportMetricsHelper|null $exportMetricHelper
	 * @param ShardManager|null $shardManager
	 * @param ProcedureDefinitionCatalog|null $procedureDefinitionCatalog
	 * @throws Exception
	 */
	public function __construct(
		SaveManagerContext         $saveManagerContext,
		?AbilitiesManager          $abilitiesManager = null,
		ExportMetricsHelper        $exportMetricHelper = null,
		ShardManager               $shardManager = null,
		ProcedureDefinitionCatalog $procedureDefinitionCatalog = null
	) {
		$this->abilitiesManager = $abilitiesManager ?? AbilitiesManager::getInstance();
		$this->context = $saveManagerContext;
		$this->exportMetricsHelper = $exportMetricHelper ?? new ExportMetricsHelper(
			$this->context->getAccountId(),
			ExportMetricsHelper::UNIT_OF_WORK_API_REQUEST,
			$shardManager,
			$this->context->isInternalRequest()
		);
		$this->shardManager = $shardManager ?? ShardManager::getInstance();
		$this->procedureDefinitionCatalog = $procedureDefinitionCatalog ?? ProcedureDefinitionCatalog::getInstance();
	}

	/**
	 * @throws Doctrine_Record_Exception
	 * @throws Doctrine_Query_Exception
	 */
	public function doCancel(piExport $export): piExport
	{
		$exportDoCancelStat = 'api.request.export.cancel.' . strtolower($this->getRoleName($this->context->getApiUser()));
		GraphiteClient::increment($exportDoCancelStat, 0.05);

		$this->exportMetricsHelper->reportExportCancelRequestTimeSeriesMetrics();

		$backgroundQueueId = $export->background_queue_id;
		piBackgroundQueueTable::getInstance()->cancelBackgroundQueuesByQueueIds(
			$this->context->getAccountId(),
			[$backgroundQueueId]
		);

		$this->getPiExportTable()->updateStatusByBackgroundQueueId(
			$backgroundQueueId,
			$this->context->getAccountId(),
			ExportStatusConstants::CANCELED
		);

		$export->refresh();
		return $export;
	}


	/**
	 * @param int $exportId
	 * @return piExport
	 * @throws Doctrine_Query_Exception
	 */
	public function validateCancel(int $exportId): piExport
	{
		$export = $this->validateExport($exportId);

		if ($export->status == ExportStatusConstants::COMPLETE ||
			$export->status == ExportStatusConstants::FAILED) {
			// Invalid export status for canceling
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_EXPORT_INVALID_STATE,
				"Export has already been completed or failed.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		if ($export->status == ExportStatusConstants::CANCELED) {
			// Already canceled
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_EXPORT_INVALID_STATE,
				"Export has already been canceled.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $export;
	}

	/**
	 * @param string $objectName
	 * @param string $actionName
	 * @param bool $isInternalRequest
	 * @param ConversionContext $conversionContext
	 * @return ProcedureDefinition
	 */
	public function verifyExportProcedure(string $objectName, string $actionName, bool $isInternalRequest, ConversionContext $conversionContext): ProcedureDefinition
	{
		$version = $conversionContext->getVersion();
		// validate the procedure name against the catalog
		$procedureDefinition = $this->getProcedureDefinitionCatalog()
			->findActionDefinitionByObjectAndName($version, $this->context->getAccountId(), $objectName, $actionName);
		// only for version 5 and above we search for "generic" export procedures
		if ($version >= 5 && !$procedureDefinition) {
			$procedureDefinition = $this->getProcedureDefinitionCatalog()
				->findActionDefinitionByObjectAndName($version, $this->context->getAccountId(), ExportManager::EXPORT_PROCEDURE, $actionName);
		}
		if (!$procedureDefinition) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_NAME,
				$this->appendAvailableProcedureMessageToError($conversionContext->getVersion(), $actionName . '.', $objectName, $isInternalRequest),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		if (!$isInternalRequest && $this->isInternalOnlyProcedure($objectName, $procedureDefinition)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_NAME,
				$this->appendAvailableProcedureMessageToError($conversionContext->getVersion(), $actionName . '.', $objectName, $isInternalRequest),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// validate that any FF required is enabled for the operation or the specific export procedure
		// also validate that the api user has required abilities to run the procedure
		$objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($version, $this->context->getAccountId(), $objectName);
		$operation = $objectDefinition->getObjectOperationDefinitionByName('export');
		if (!$operation ||
			!$this->isFeatureEnabled($procedureDefinition) ||
			!$this->isOperationEnabled($operation) ||
			!$this->canUserExportObject($operation)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_NAME,
				$this->appendAvailableProcedureMessageToError($conversionContext->getVersion(), $actionName . '.', $objectName, $isInternalRequest),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $procedureDefinition;
	}

	/**
	 * Gets a list of procedure names that the user has access to.
	 *
	 * Careful using this method! It's not really efficient since it must load each of the procedure definitions and
	 * check against the account settings and user's abilities. If you need to check if a single procedure is allowed,
	 * it's easier/faster/better to load the procedure definition from the catalog directly.
	 *
	 * @param int $version
	 * @param StaticObjectDefinition $objectDefinition
	 * @param bool $isInternalRequest
	 * @return array
	 */
	public function getAvailableProcedureNames(int $version, StaticObjectDefinition $objectDefinition, bool $isInternalRequest = true): array
	{
		$objectName = $objectDefinition->getType();
		$procedureNames = $this->getProcedureDefinitionCatalog()->getActionDefinitionNamesForObject($version, $this->context->getAccountId(), $objectName);
		$allowedProcedureNames = [];
		foreach ($procedureNames as $thisProcedureName) {
			$thisProcedureDefinition = $this->getProcedureDefinitionCatalog()
				->findActionDefinitionByObjectAndName($version, $this->context->getAccountId(), $objectName, $thisProcedureName);
			if (!$thisProcedureDefinition) {
				continue;
			}

			if ($this->isProcedureAvailable($objectDefinition, $thisProcedureDefinition, $isInternalRequest)) {
				$allowedProcedureNames[] = $thisProcedureName;
			}
		}
		return $allowedProcedureNames;
	}


	/**
	 * @param string $objectName
	 * @param ProcedureDefinition $procedureDefinition
	 * @return bool
	 */
	private function isInternalOnlyProcedure(string $objectName, ProcedureDefinition $procedureDefinition): bool
	{
		if (!array_key_exists($objectName, self::DISALLOW_LIST_FOR_EXTERNAL_REQUESTS)) {
			return false;
		}

		$internalOnlyProcedures = self::DISALLOW_LIST_FOR_EXTERNAL_REQUESTS[$objectName];
		if (!in_array($procedureDefinition->getName(), $internalOnlyProcedures)) {
			return false;
		}

		return true;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @return bool
	 */
	private function canUserExportObject(ObjectOperationDefinition $operationDefinition): bool
	{
		return ($this->abilitiesManager->evaluateAccessRule(
			$operationDefinition->getAbilities(),
			$this->getUserAbilities()
		));
	}


	/**
	 * @param ProcedureDefinition $procedureDefinition
	 * @return bool
	 */
	private function isFeatureEnabled(ProcedureDefinition $procedureDefinition): bool
	{
		return AccountSettingsManager::getInstance($this->context->getAccountId())
			->evaluateFeatureFlagAccessRule($procedureDefinition->getRequiredFeatureFlags());
	}

	/**
	 * @param StaticObjectDefinition $objectDefinition
	 * @param ProcedureDefinition $procedureDefinition
	 * @param bool $isInternalRequest
	 * @return bool
	 */
	private function isProcedureAvailable(
		StaticObjectDefinition $objectDefinition,
		ProcedureDefinition    $procedureDefinition,
		bool                   $isInternalRequest = false
	): bool {
		$exportOperation = $objectDefinition->getObjectOperationDefinitionByName('export');
		return $exportOperation &&
			$this->canUserExportObject($exportOperation) &&
			($isInternalRequest || !$this->isInternalOnlyProcedure($objectDefinition->getType(), $procedureDefinition));
	}

	/**
	 * @param int $version
	 * @param string $errorMessage
	 * @param string $objectName
	 * @param bool $isInternalRequest
	 * @return string
	 */
	private function appendAvailableProcedureMessageToError(
		int    $version,
		string $errorMessage,
		string $objectName,
		bool   $isInternalRequest = false
	): string {
		// get the list of procedures available to the user
		$objectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($objectName);
		$allowedProcedureNames = $this->getAvailableProcedureNames($version, $objectDefinition, $isInternalRequest);
		if (!empty($allowedProcedureNames)) {
			$errorMessage .= " Procedure name must be one of the following: " . stringTools::generateHumanReadableList($allowedProcedureNames);
		}
		return $errorMessage;
	}


	/**
	 * @return ProcedureDefinitionCatalog
	 */
	private function getProcedureDefinitionCatalog(): ProcedureDefinitionCatalog
	{
		return $this->procedureDefinitionCatalog;
	}

	/**
	 * @return array
	 */
	public function getUserAbilities(): array
	{
		return $this->context->getApiUser()->loadCredentials();
	}

	/**
	 * Common validation used from v3/v4 and v5 request managers to validate the create call
	 * @param ObjectDefinition $objectDefinition
	 * @param string[] $fieldNames
	 * @param ExportProcedureInputBuilder $procedureInputBuilder
	 * @param bool $includeByteOrderMark
	 * @param int|null $maxFileSizeBytes
	 * @return ExportInput
	 */
	public function validateCreate(
		ObjectDefinition            $objectDefinition,
		?array                      $fieldNames,
		ExportProcedureInputBuilder $procedureInputBuilder,
		bool                        $includeByteOrderMark = false,
		?int                        $maxFileSizeBytes = null
	): ExportInput {
		$fields = null;
		if (!empty($fieldNames)) {
			$fields = $this->validateObjectFieldsForExport($fieldNames, $objectDefinition);
		}

		$exportInputBuilder = ExportInputBuilder::create()
			->withObjectDefinition($objectDefinition)
			->withProcedureInput($procedureInputBuilder);
		if ($fields) {
			$exportInputBuilder->withFields($fields);
		}
		if ($includeByteOrderMark) {
			$exportInputBuilder->withByteOrderMark();
		}
		if (!empty($maxFileSizeBytes)) {
			$exportInputBuilder->withMaxFileSizeBytes($maxFileSizeBytes);
		}

		return $exportInputBuilder->build();
	}

	/**
	 * @param int $version
	 * @param string $object
	 * @param bool $isInternalRequest
	 * @return false|ObjectDefinition
	 * @throws Exception
	 */
	public function validateObjectProperty(int $version, string $object, bool $isInternalRequest = false): ?ObjectDefinition
	{
		$validObjectName = $this->findValidObjectName($version, $object, $isInternalRequest);
		// make sure to return the API name in the correct case
		return ObjectDefinitionCatalog::getInstance()
			->findObjectDefinitionByObjectType($version, $this->context->getAccountId(), $validObjectName);
	}

	/**
	 * Validates the "object" property on the inputRep.
	 * @param int $version
	 * @param string $object the object value from the user.
	 * @param bool $isInternalRequest is the request internal
	 * @return string Returns the value for object that is of the correct case so that later conditionals can use regular
	 * comparisons instead of adding case checking logic.
	 * @throws Exception
	 */
	private function findValidObjectName(int $version, string $object, bool $isInternalRequest = false): string
	{
		$objectNames = $this->getProcedureDefinitionCatalog()->getObjectNamesWithActions($version, $this->context->getAccountId());
		if (isset($objectNames[ExportManager::EXPORT_PROCEDURE])) {
			unset($objectNames[ExportManager::EXPORT_PROCEDURE]);
		}
		foreach ($objectNames as $key => $objectName) {
			$objectDefinition = $this->getStaticDefinitionCatalog()->findObjectDefinitionByObjectType($objectName);
			$procedureNames = $this->getAvailableProcedureNames($version, $objectDefinition, $isInternalRequest);
			if (empty($procedureNames)) {
				unset($objectNames[$key]);
			}
		}


		foreach ($objectNames as $objectName) {
			if (strtolower($object) == strtolower($objectName)) {
				return $objectName;
			}
		}

		throw new ApiException(
			ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
			"The 'object' property must be " . stringTools::generateHumanReadableList($objectNames) . ".",
			RESTClient::HTTP_BAD_REQUEST
		);
	}


	/**
	 * @param string[] $fields
	 * @param ObjectDefinition $objectDefinition
	 * @return FieldDefinition[]
	 */
	public function validateObjectFieldsForExport(array $fields, ObjectDefinition $objectDefinition): array
	{
		$unknownFields = [];
		$fieldsDefinitions = [];

		foreach ($fields as $fieldName) {
			$fieldsDefinition = $objectDefinition->getFieldByName($fieldName);
			// for built-in fields, prevent exporting array or maps. They are allowed for custom fields
			if (!$fieldsDefinition  ||
				(!$fieldsDefinition->isCustom() &&
					($fieldsDefinition->getDataType() instanceof ArrayDataType ||
					$fieldsDefinition->getDataType() instanceof MapDataType))) {
				$unknownFields[] = $fieldName;
			} else {
				$fieldsDefinitions[] = $fieldsDefinition;
			}
		}

		// Populate these metrics now so that they will appear in the ApiMetrics log even if API exception is thrown
		$this->exportMetricsHelper->collectFieldCountMetricsFromFieldDefinitions(
			$fieldsDefinitions,
			$this->context->getVersion()
		);
		$this->exportMetricsHelper->addCollectedFieldCountsToExportMetricsObject($this->context->getMetrics());

		if (!empty($unknownFields)) {
			$message = implode(", ", $unknownFields);
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_FIELDS,
				$message,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		return $fieldsDefinitions;
	}

	/**
	 * W-9675185 determining which role calls which route
	 * if there is a custom role, just name the role customs
	 * to be removed once data is collected
	 * @param $apiUserRoleId
	 * @return ?string
	 */
	public function getApiUserRoleName($apiUserRoleId): ?string
	{
		$apiUserRoleName = piUserTable::getDefaultRoleName($apiUserRoleId);
		if (is_null($apiUserRoleName)) {
			$apiUserRoleName = 'Custom';
		}
		return str_replace(" ", "", $apiUserRoleName);
	}

	/**
	 * @throws Exception
	 */
	public function doCreate($accountId, $userId, $apiUserRoleId, $exportInput): piExport
	{
		$apiUserRoleName = $this->getApiUserRoleName($apiUserRoleId);

		$exportDoCreateStat = 'api.request.export.create.' . strtolower($apiUserRoleName);
		GraphiteClient::increment($exportDoCreateStat, 0.05);

		$userConversionContext = $this->context->getConversionContext();
		$shardConnection = $this->shardManager->getDoctrineShardConnection();
		$shardConnection->beginTransaction();
		try {
			$requestType = $this->context->isInternalRequest() ? ExportRequestTypeConstants::INTERNAL : ExportRequestTypeConstants::EXTERNAL;
			$serviceName = $this->context->getServiceName();

			$export = $this->getExportManager()->startExportWithProcedure(
				$accountId,
				$this->context->getVersion(),
				$userId,
				$exportInput,
				$requestType,
				$userConversionContext,
				$serviceName
			);

			$shardConnection->commit();
		} catch (Exception $exc) {
			$shardConnection->rollback();
			$this->exportMetricsHelper->addCollectedRequestTypeAndApiVersionToExportMetricsObject(
				$this->context->getMetrics()
			);
			$this->exportMetricsHelper->reportFailedExportCreateRequestTimeSeriesMetrics();

			// If a known export exception is thrown convert it to the API exception, otherwise throw a generic exception.
			if ($exc instanceof ExportMissingRequiredProcedureArgumentsException) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_MISSING_REQUIRED_PROCEDURE_ARGUMENT,
					'Missing required arguments for procedure: ' . stringTools::generateHumanReadableList($exc->getMissingArgumentNames(), 'and'),
					RESTClient::HTTP_BAD_REQUEST,
					$exc
				);
			} elseif ($exc instanceof ExportInvalidProcedureArgumentException) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_ARGUMENT,
					'Invalid value specified for argument ' . $exc->getArgumentName(),
					RESTClient::HTTP_BAD_REQUEST,
					$exc
				);
			} elseif ($exc instanceof ExportUnknownProcedureArgumentsException) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_UNKNOWN_PROCEDURE_ARGUMENT,
					'Unknown arguments for procedure: ' . stringTools::generateHumanReadableList($exc->getArgumentNames(), 'and'),
					RESTClient::HTTP_BAD_REQUEST,
					$exc
				);
			} else {
				throw $exc;
			}
		}

		// calling collection method to get exportId and default field count if fields were not specified in request
		$this->exportMetricsHelper->collectAllRequestMetricsFromExportObject($export, $this->context->getVersion());
		$this->exportMetricsHelper->addAllCollectedMetricsToExportMetricsObject($this->context->getMetrics());
		$this->exportMetricsHelper->reportExportCreateRequestTimeSeriesMetrics();
		return $export;
	}

	/**
	 * @return ExportManager
	 */
	public function getExportManager(): ExportManager
	{
		if (!$this->exportManager) {
			$this->exportManager = new ExportManager();
		}
		return $this->exportManager;
	}

	/**
	 * @param $apiMetrics
	 * @param $validObjectName
	 * @return void
	 */
	public function addCollectedObjectNameToExportMetricsObject($apiMetrics, $validObjectName): void
	{
		$this->exportMetricsHelper->addCollectedObjectNameToExportMetricsObject(
			$apiMetrics,
			$validObjectName
		);
	}

	/**
	 * @param piExport $export
	 * @param piExportFile $exportFile
	 * @param sfResponse $response
	 * @return void
	 */
	public function doDownloadResults(piExport $export, piExportFile $exportFile, sfResponse $response)
	{
		$exportDoDownloadStat = 'api.request.export.download.' . $this->getRoleName($this->context->getApiUser());
		GraphiteClient::increment($exportDoDownloadStat, 0.05);

		$this->getExportManager()->transferExportResultToResponse($export, $exportFile, $response);
		$this->exportMetricsHelper->reportExportDownloadRequestTimeSeriesMetrics();
	}

	/**
	 * @param int $exportId
	 * @param int $exportFileId
	 * @return array
	 * @throws Doctrine_Query_Exception
	 */
	public function validateDownloadResults(int $exportId, int $exportFileId): array
	{
		$exportFile = $this->validateExportFile($exportFileId, $exportId);

		$export = $this->validateExport($exportId);
		$export->expireExportInMemory('7 days ago');

		if ($export->is_expired || ($export->status != ExportStatusConstants::COMPLETE)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_EXPORT_INVALID_STATE,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}

		return [
			$export,
			$exportFile
		];
	}

	/**
	 * @param int $exportId
	 * @return piExport
	 * @throws Doctrine_Query_Exception
	 */
	public function validateExport(int $exportId): piExport
	{
		$export = $this->loadExport($exportId);

		// Check request type matches export type
		if (!$this->canRequestTypeAccessExport($export)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}

		// make sure the user has the correct abilities to view the export
		$procedureDefinition = $this->loadProcedureDefinitionFromExport($export);
		$this->ensureUserHasAccessToExport($procedureDefinition, $export);

		return $export;
	}

	/**
	 * @param int $exportFileId
	 * @param int $exportId
	 * @return piExportFile
	 * @throws Doctrine_Query_Exception
	 */
	private function validateExportFile(int $exportFileId, int $exportId): piExportFile
	{
		PardotLogger::getInstance()->addTags(
			[
				"export_id" => $exportId,
				"export_file_id" => $exportFileId,
			]
		);
		$exportFile = $this->getPiExportFileTable()->retrieveById($exportFileId, $this->context->getAccountId());
		if (!$exportFile) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				"Unable to find export file with the given ID",
				RESTClient::HTTP_NOT_FOUND
			);
		}

		if ($exportFile->export_id != $exportId) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_ID,
				"Export file does not belong to this Export",
				RESTClient::HTTP_NOT_FOUND
			);
		}
		return $exportFile;
	}

	/**
	 * @param int $exportId
	 * @return piExport
	 * @throws Doctrine_Query_Exception
	 */
	public function loadExport(int $exportId): piExport
	{
		if (!$exportId) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PARAMETERS,
				'Missing required parameter "id"',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$export = $this->getPiExportTable()->retrieveById($exportId, $this->context->getAccountId());
		if (!$export) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}

		$this->exportMetricsHelper->collectBasicRequestMetricsFromExportObject($export, $this->context->getVersion());
		$this->exportMetricsHelper->addAllCollectedMetricsToExportMetricsObject($this->context->getMetrics());

		return $export;
	}

	/**
	 * @param piExport $export
	 * @return ProcedureDefinition
	 */
	public function loadProcedureDefinitionFromExport(piExport $export): ProcedureDefinition
	{
		$objectName = $export->object ? apiTools::getObjectNameFromId($export->object) : apiTools::UNKNOWN_OBJECT;
		if (strcasecmp($objectName, apiTools::UNKNOWN_OBJECT) == 0) {
			PardotLogger::getInstance()->addTags([
				"export_id" => $export->id,
				"object_id" => $export->object,
			]);
			PardotLogger::getInstance()->error("Export does not have a valid object associated: " . $export->object);
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNKNOWN,
				null,
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		if (!$export->procedure_name) {
			PardotLogger::getInstance()->addTags([
				"export_id" => $export->id,
				"object_id" => $export->object,
				"procedure_name" => $export->procedure_name,
			]);
			PardotLogger::getInstance()->error("Export is not associated to a procedure.");
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNKNOWN,
				null,
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		$procedureDefinition = $this->findProcedureDefinition($objectName, $export->procedure_name);

		if (!$procedureDefinition) {
			PardotLogger::getInstance()->addTags([
				"export_id" => $export->id,
				"object_id" => $export->object,
				"procedure_name" => $export->procedure_name,
			]);
			PardotLogger::getInstance()
				->error("Export is not associated to a valid procedure: " . $export->procedure_name);
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNKNOWN,
				null,
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}
		return $procedureDefinition;
	}

	/**
	 * @param ProcedureDefinition $procedureDefinition
	 * @param piExport $piExport
	 * @return void
	 */
	public function ensureUserHasAccessToExport(ProcedureDefinition $procedureDefinition, piExport $piExport): void
	{
		$userAbilities = $this->getUserAbilities();

		// A user has access to the export
		//   - if the user has Admin > Exports > View OR
		//   - if the user has all the required abilities and created the export
		// otherwise an error should be thrown
		if (!in_array(Abilities::ADMIN_EXPORTS_VIEW, $userAbilities)) {
			// make sure that only the user that created the export is reading the export
			if ($this->context->getApiUser()->id != $piExport->created_by) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
					null,
					RESTClient::HTTP_NOT_FOUND
				);
			}

			$objectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType(apiTools::getObjectNameFromId($piExport->object));
			$exportOperation = $objectDefinition->getObjectOperationDefinitionByName('export');
			// make sure the user has the correct abilities to view the procedure
			if (!$exportOperation || !$this->abilitiesManager->evaluateAccessRule($exportOperation->getAbilities(), $userAbilities)) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
					null,
					RESTClient::HTTP_NOT_FOUND
				);
			}
		}
	}

	/**
	 * @param piExport $export
	 * @return bool
	 */
	public function canRequestTypeAccessExport(piExport $export): bool
	{
		$exportType = $export->request_type;
		if ($exportType == ExportRequestTypeConstants::EXTERNAL && $this->context->isInternalRequest()) {
			return false;
		}
		if ($exportType == ExportRequestTypeConstants::INTERNAL && !$this->context->isInternalRequest()) {
			return false;
		}
		return true;
	}

	/**
	 * @param piUser $apiUser
	 * @return string
	 */
	private function getRoleName(piUser $apiUser): string
	{
		$apiUserRoleId = $apiUser->getRole();
		$apiUserRoleName = piUserTable::getDefaultRoleName($apiUserRoleId);
		if (is_null($apiUserRoleName)) {
			$apiUserRoleName = 'Custom';
		}
		return str_replace(" ", "", $apiUserRoleName);
	}

	/**
	 * @return piExportFileTable
	 */
	private function getPiExportFileTable(): piExportFileTable
	{
		if (!$this->piExportFileTable) {
			$this->piExportFileTable = piExportFileTable::getInstance();
		}
		return $this->piExportFileTable;
	}

	/**
	 * @return piExportTable
	 */
	private function getPiExportTable(): piExportTable
	{
		if (!$this->piExportTable) {
			$this->piExportTable = piExportTable::getInstance();
		}
		return $this->piExportTable;
	}

	/**
	 * @param string $objectName
	 * @param string $procedure_name
	 * @return false|ProcedureDefinition
	 */
	private function findProcedureDefinition(string $objectName, string $procedure_name)
	{
		foreach ([$objectName, ExportManager::EXPORT_PROCEDURE] as $name) {
			$procedureDefinition = $this->procedureDefinitionCatalog
				->findActionDefinitionByObjectAndName(
					3, // the database stores the procedure name in v3 format
					$this->context->getApiUser()->account_id,
					$name,
					$procedure_name
				);
			if ($procedureDefinition) {
				return $procedureDefinition;
			}
		}
		return false;
	}

	private function isOperationEnabled(ObjectOperationDefinition $operation): bool
	{
		return AccountSettingsManager::getInstance($this->context->getAccountId())
			->evaluateFeatureFlagAccessRule($operation->getFeatureFlags());
	}

	/**
	 * @return StaticObjectDefinitionCatalog|null
	 */
	private function getStaticDefinitionCatalog(): ?StaticObjectDefinitionCatalog
	{
		if (!$this->objectDefinitionCatalog) {
			$this->objectDefinitionCatalog = StaticObjectDefinitionCatalog::getInstance();
		}
		return $this->objectDefinitionCatalog;
	}
}

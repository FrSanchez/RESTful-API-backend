<?php

namespace Api\Config\Objects\Export;

use Api\Actions\ActionArgumentsMarshaller as ExportProcedureArgumentsMarshaller;
use Api\DataTypes\ConversionContext;
use Api\Exceptions\ApiException;
use Api\Export\ExportManager;
use Api\Export\ProcedureDefinition;
use Api\Export\ProcedureDefinitionCatalog;
use ApiErrorLibrary;
use Exception;
use RESTClient;
use stringTools;

class ExportRepresentationHelper
{
	/**
	 * @param string $objectName
	 * @param string $procedure_name
	 * @param int $accountId
	 * @return false|ProcedureDefinition
	 * @throws Exception
	 */
	private function findProcedureDefinition(string $objectName, string $procedure_name, int $accountId)
	{
		$procedureDefinitionCatalog = ProcedureDefinitionCatalog::getInstance();
		foreach ([$objectName, ExportManager::EXPORT_PROCEDURE] as $name) {
			$procedureDefinition = $procedureDefinitionCatalog
				->findActionDefinitionByObjectAndName(
					3, // the database stores the procedure name in v3 format
					$accountId,
					$name,
					$procedure_name
				);
			if ($procedureDefinition) {
				return $procedureDefinition;
			}
		}
		return false;
	}
	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $objectName
	 * @param string $arguments
	 * @param string $procedureName
	 * @return array
	 * @throws Exception
	 */
	public function getProcedureData(int $version, int $accountId, string $objectName, string $arguments, string $procedureName): array
	{
		$procedureDefinition = $this->findProcedureDefinition($objectName, $procedureName, $accountId);
		if (!$procedureDefinition) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_NAME,
				null,
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}
		$exportArgumentsRepresentation = ExportProcedureArgumentsMarshaller::getInstance()->deserializeFromJson(
			$procedureDefinition,
			$arguments,
			ConversionContext::createDefault($version)
		);
		$exportArguments = [];
		foreach ($exportArgumentsRepresentation as $argumentName => $argumentValue) {
			$argumentDefinition = $procedureDefinition->getArgumentByName($argumentName);
			if ($argumentDefinition) {
				$exportArguments[lcfirst(stringTools::camelize($argumentName))] = $argumentValue;
			}
		}

		$procedureName = ucfirst($objectName) . '/' . stringTools::camelize($procedureName);
		return ['name' => $procedureName, 'arguments' => $exportArguments];
	}

}

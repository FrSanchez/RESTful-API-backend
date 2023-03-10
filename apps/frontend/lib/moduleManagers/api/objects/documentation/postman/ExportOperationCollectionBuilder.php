<?php

namespace Api\Objects\Postman;

use Api\Actions\StaticActionDefinition;
use Api\Actions\StaticActionDefinitionCatalog;
use Api\Export\ExportManager;
use Api\Export\ProcedureDefinitionCatalog;
use Api\Objects\StaticObjectDefinition;
use Api\Objects\StaticObjectDefinitionCatalog;
use Api\Objects\StaticObjectDefinitionImpl;
use Exception;
use stringTools;

class ExportOperationCollectionBuilder extends OperationCollectionBuilder
{
	private ?StaticActionDefinitionCatalog $procedureDefinitionCatalog = null;
	private array $procedures;
	/**
	 * @return string[]
	 * @throws Exception
	 */
	protected function getOperationNames(): array
	{
		$this->procedures = [];
		$operations = [];
		$objectNames = $this->getProcedureDefinitionCatalog()->getObjectNamesWithActions();
		foreach ($objectNames as $objectName) {
			$procedures = $this->getProcedureDefinitionCatalog()->getActionDefinitionNamesForObject($objectName);
			foreach ($procedures as $procedure) {
				$name = "$objectName:$procedure";
				$operations[$name] = null;
				$this->procedures[$name] = true;
			}
		}
		$this->getGenericOperations($operations);
		$operations = array_keys($operations);
		sort($operations);
		return array_merge($operations, ['read', 'query', 'download-results']);
	}

	private function getGenericOperations(array &$operations)
	{
		$procedures = $this->getProcedureDefinitionCatalog()->getActionDefinitionNamesForObject(ExportManager::EXPORT_PROCEDURE);
		foreach (StaticObjectDefinitionCatalog::getInstance()->getObjectNames() as $objectName) {
			$objectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($objectName);
			$exportOperation = $objectDefinition->getObjectOperationDefinitionByName('export');
			if (!$exportOperation || $exportOperation->isInternalOnly()) {
				echo "$objectName : skipped\n";
				continue;
			}
			foreach ($procedures as $procedure) {
				$name = "$objectName:$procedure";
				if (!array_key_exists($name, $operations)) {
					$operations[$name] = null;
					$this->procedures[$name] = true;
				}
			}
		}
	}

	public function canDoOperation($operationName): bool
	{
		if (stringTools::contains($operationName, 'Export') || stringTools::contains($operationName, 'Import')) {
			return false;
		}
		return true;
	}

	protected function canDoAction(StaticActionDefinition $actionDefinition): bool
	{
		if ($actionDefinition->getName() == 'cancel') {
			return true;
		}
		return parent::canDoAction($actionDefinition);
	}

	/**
	 * @param string $operationName
	 * @return OperationBuilder|null
	 * @throws Exception
	 */
	public function getOperationBuilder(string $operationName): ?OperationBuilder
	{
		if (array_key_exists($operationName, $this->procedures)) {
			$objectAndProcedure = preg_split("/:/", $operationName, -1, PREG_SPLIT_NO_EMPTY);
			$procedureDefinition = $this->getProcedureDefinitionCatalog()->findStaticActionDefinitionByObjectAndName($objectAndProcedure[0], $objectAndProcedure[1]);
			if (!$procedureDefinition) {
				$procedureDefinition = $this->getProcedureDefinitionCatalog()
					->findStaticActionDefinitionByObjectAndName(ExportManager::EXPORT_PROCEDURE, $objectAndProcedure[1]);
			}
			if ($procedureDefinition->isInternalOnly() || !$this->canDoAction($procedureDefinition)) {
				return null;
			}
			$exportObjectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($objectAndProcedure[0]);
			if (!$exportObjectDefinition || $exportObjectDefinition->isSingleton()) {
				return null;
			}
			return new ExportCustomCreateProcedureBuilder($procedureDefinition, $this->objectDefinition, $exportObjectDefinition, $this->version);
		}
		switch ($operationName) {
			case 'download-results':
				return new ExportDownloadResultsBuilder($this->objectDefinition, $this->version);
			default:
				return parent::getOperationBuilder($operationName); // TODO: Change the autogenerated stub
		}
	}

	/**
	 * @return StaticActionDefinitionCatalog
	 * @throws Exception
	 */
	private function getProcedureDefinitionCatalog(): StaticActionDefinitionCatalog
	{
		if (!$this->procedureDefinitionCatalog) {
			$this->procedureDefinitionCatalog = ProcedureDefinitionCatalog::createProcedureStaticActionDefinitionCatalog();
		}
		return $this->procedureDefinitionCatalog;
	}
}

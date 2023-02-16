<?php

namespace Api\Objects\Postman;

use AlwaysFalseFeatureFlagAccessRule;
use AlwaysTrueFeatureFlagAccessRule;
use Api\Actions\FileSystemStaticActionDefinitionCatalog;
use Api\Actions\StaticActionDefinition;
use Api\Actions\StaticActionDefinitionCatalog;
use Api\Objects\ObjectActions\ObjectActionDefinitionCatalog;
use Api\Objects\StaticObjectDefinition;
use FeatureFlagAccessRule;
use FeatureFlagGroup;
use RuntimeException;

class OperationCollectionBuilder
{
	protected StaticObjectDefinition $objectDefinition;
	protected int $version;
	private FeatureFlagGroup $featureFlagGroup;

	public function __construct(StaticObjectDefinition $objectDefinition, int $version, FeatureFlagGroup  $featureFlagGroup)
	{
		$this->objectDefinition = $objectDefinition;
		$this->version = $version;
		$this->featureFlagGroup = $featureFlagGroup;
	}

	/**
	 * @return string[]
	 */
	protected function getOperationNames(): array
	{
		return ['create', 'delete', 'read', 'query', 'update'];
	}

	/**
	 * @return array
	 */
	protected function getActionsNames(): array
	{
		$objectActionCatalog = $this->getObjectActionCatalog();
		$recordActionCatalog = $this->getRecordActionCatalog();
		return array_merge(
			$recordActionCatalog->getActionDefinitionNamesForObject($this->objectDefinition->getType()),
			$objectActionCatalog->getActionDefinitionNamesForObject($this->objectDefinition->getType())
		);
	}

	/**
	 * @param $operationName
	 * @return bool
	 */
	protected function canDoOperation($operationName): bool
	{
		$operation = $this->objectDefinition->getObjectOperationDefinitionByName($operationName);
		if (is_bool($operation)) {
			return $operation;
		}
		return $this->evaluateFeatureFlagAccessRule($operation->getFeatureFlags());
	}

	/**
	 * Determines if the account has the settings to pass the access rule. If false is returned, the account *does not*
	 * have the required feature flags defined in the access rule.
	 * @param FeatureFlagAccessRule $accessRule
	 * @return bool
	 */
	public function evaluateFeatureFlagAccessRule(FeatureFlagAccessRule $accessRule): bool
	{
		// shortcut so that we don't need execute anything (or even retrieve feature flag values)
		if ($accessRule instanceof AlwaysTrueFeatureFlagAccessRule) {
			return true;
		} elseif ($accessRule instanceof  AlwaysFalseFeatureFlagAccessRule) {
			return false;
		}

		return $accessRule->evaluate($this->featureFlagGroup);
	}

	/**
	 * @param StaticActionDefinition $actionDefinition
	 * @return bool
	 */
	protected function canDoAction(StaticActionDefinition $actionDefinition): bool
	{
		return $this->evaluateFeatureFlagAccessRule($actionDefinition->getRequiredFeatureFlags());
	}

	/**
	 * @return Operation[]
	 */
	public function build(): array
	{
		$operationsAndActions = [];
		foreach ($this->getOperationNames() as $operationName) {
			if ($this->canDoOperation($operationName)) {
				$operation = $this->buildOperationAndAction($operationName);
				if ($operation) {
					$operationsAndActions[] = $operation;
				}
			}
		}
		foreach ($this->getActionsNames() as $actionName) {
			$action = $this->buildOperationAndAction($actionName);
			if ($action) {
				$operationsAndActions[] = $action;
			}
		}
		return $operationsAndActions;
	}

	/**
	 * @param string $operationName
	 * @return Operation|null
	 */
	protected function buildOperationAndAction(string $operationName): ?Operation
	{
		$builder = $this->getOperationBuilder($operationName);
		if (!$builder) {
			return null;
		}
		return $builder->build();
	}

	/**
	 * @param string $operationName
	 * @return OperationBuilder
	 */
	protected function getOperationBuilder(string $operationName): ?OperationBuilder
	{
		switch ($operationName) {
			case 'create':
				$builder = new CreateOperationBuilder($this->objectDefinition, $this->version);
				break;
			case 'delete':
				$builder = new DeleteOperationBuilder($this->objectDefinition, $this->version);
				break;
			case 'read':
				$builder = new ReadOperationBuilder($this->objectDefinition, $this->version);
				break;
			case 'query':
				$builder = new QueryOperationBuilder($this->objectDefinition, $this->version);
				break;
			case 'update':
				$builder = new UpdateOperationBuilder($this->objectDefinition, $this->version);
				break;
			default:
				$builder = $this->getActionBuilder($operationName);
		}
		return $builder;
	}

	/**
	 * @param string $actionName
	 * @return void
	 */
	private function getActionBuilder(string $actionName): ?OperationBuilder
	{
		$builder = null;
		$isObjectAction = false;
		$actionDefinition = $this->getObjectActionCatalog()
			->findStaticActionDefinitionByObjectAndName($this->objectDefinition->getType(), $actionName);
		if (!$actionDefinition) {
			$isObjectAction = true;
			$actionDefinition = $this->getRecordActionCatalog()
				->findStaticActionDefinitionByObjectAndName($this->objectDefinition->getType(), $actionName);
		}
		if ($actionDefinition instanceof StaticActionDefinition) {
			if ($this->canDoAction($actionDefinition)) {
				$builder = new ActionBuilder($actionDefinition, $isObjectAction, $this->objectDefinition, $this->version);
			}
		} else {
			throw new RuntimeException($actionName . ' is not defined for ' . $this->objectDefinition->getType());
		}
		return $builder;
	}

	/**
	 * @return StaticActionDefinitionCatalog|null
	 */
	private function getObjectActionCatalog(): ?StaticActionDefinitionCatalog
	{
		if (!isset($this->objectActionCatalog)) {
			$this->objectActionCatalog = ObjectActionDefinitionCatalog::createObjectActionStaticActionDefinitionCatalog();
		}
		return $this->objectActionCatalog;
	}

	/**
	 * @return ?StaticActionDefinitionCatalog
	 */
	private function getRecordActionCatalog(): ?StaticActionDefinitionCatalog
	{
		if (!isset($this->recordActionCatalog)) {
			$objectConfigPath = FileSystemStaticActionDefinitionCatalog::getObjectConfigDirectoryPath();
			$actionFolderName = "recordActions";

			$this->recordActionCatalog = new FileSystemStaticActionDefinitionCatalog(
				$objectConfigPath,
				$actionFolderName
			);
		}
		return $this->recordActionCatalog;
	}
}

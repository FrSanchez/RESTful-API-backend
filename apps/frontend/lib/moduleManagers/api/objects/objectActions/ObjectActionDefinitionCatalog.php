<?php
namespace Api\Objects\ObjectActions;

use AccountSettingsManagerFactory;
use Api\Actions\FileSystemStaticActionDefinitionCatalog;
use Api\Actions\StaticActionDefinitionCatalog;

/**
 * Catalog of {@see ObjectActionDefinition} instances for a given account within a given version.
 */
class ObjectActionDefinitionCatalog
{
	private StaticActionDefinitionCatalog $staticActionDefinitionCatalog;
	private AccountSettingsManagerFactory $accountSettingsManagerFactory;

	private array $objectNames = [];
	private array $lowercaseObjectNamesToActionNamesToActionDefinition = [];

	/**
	 * ProcedureDefinitionCatalog constructor.
	 * @param StaticActionDefinitionCatalog $staticActionDefinitionCatalog
	 * @param AccountSettingsManagerFactory $accountSettingsManagerFactory
	 */
	public function __construct(
		StaticActionDefinitionCatalog $staticActionDefinitionCatalog,
		AccountSettingsManagerFactory $accountSettingsManagerFactory
	) {
		$this->staticActionDefinitionCatalog = $staticActionDefinitionCatalog;
		$this->accountSettingsManagerFactory = $accountSettingsManagerFactory;
	}

	/**
	 * Factory method for creating the {@see StaticActionDefinitionCatalog} for object actions.
	 * @return StaticActionDefinitionCatalog
	 */
	public static function createObjectActionStaticActionDefinitionCatalog(): StaticActionDefinitionCatalog
	{
		$objectConfigPath = FileSystemStaticActionDefinitionCatalog::getObjectConfigDirectoryPath();
		$actionFolderName = "objectActions";

		return new FileSystemStaticActionDefinitionCatalog(
			$objectConfigPath,
			$actionFolderName
		);
	}

	/**
	 * @param int $version
	 * @param int $accountId
	 * @return array
	 */
	public function getObjectNamesWithActions(int $version, int $accountId): array
	{
		$this->initialize($version, $accountId);
		return $this->objectNames[$version][$accountId];
	}

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $objectName
	 * @param string $actionName
	 * @return false|ObjectActionDefinition
	 */
	public function findActionDefinitionByObjectAndName(int $version, int $accountId, string $objectName, string $actionName)
	{
		$this->initialize($version, $accountId);
		$lowercaseObjectName = strtolower($objectName);
		if (!array_key_exists($lowercaseObjectName, $this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId])) {
			return false;
		}

		$lowercaseActionName = strtolower($actionName);
		return $this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId][$lowercaseObjectName][$lowercaseActionName]
			?? false;
	}

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $objectName
	 * @return array
	 */
	public function getActionDefinitionNamesForObject(int $version, int $accountId, string $objectName): array
	{
		$this->initialize($version, $accountId);
		$lowercaseObjectName = strtolower($objectName);
		if (!array_key_exists($lowercaseObjectName, $this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId])) {
			return [];
		}

		return array_keys($this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId][$lowercaseObjectName]);
	}

	private function initialize(int $version, int $accountId): void
	{
		if (isset($this->objectNames[$version][$accountId])) {
			return;
		}
		$this->objectNames[$version][$accountId] = [];
		$this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId] = [];

		$accountSettingsManager = $this->accountSettingsManagerFactory->getInstance($accountId);
		$objectNamesWithActions = $this->staticActionDefinitionCatalog->getObjectNamesWithActions();
		foreach ($objectNamesWithActions as $objectName) {
			$staticActionNames = $this->staticActionDefinitionCatalog->getActionDefinitionNamesForObject($objectName);
			foreach ($staticActionNames as $actionName) {
				$staticActionDefinition = $this->staticActionDefinitionCatalog->findStaticActionDefinitionByObjectAndName($objectName, $actionName);

				if (!$accountSettingsManager->evaluateFeatureFlagAccessRule($staticActionDefinition->getRequiredFeatureFlags())) {
					// Feature flag is not enabled for the current account
					continue;
				}

				$lowercaseObjectName = strtolower($objectName);
				$lowercaseActionName = strtolower($actionName);
				$actionDefinition = new ObjectActionDefinition($staticActionDefinition, $accountId, $version);

				$this->objectNames[$version][$accountId][$objectName] = $objectName;
				$this->lowercaseObjectNamesToActionNamesToActionDefinition[$version][$accountId][$lowercaseObjectName][$lowercaseActionName] =
					$actionDefinition;
			}
		}
	}
}

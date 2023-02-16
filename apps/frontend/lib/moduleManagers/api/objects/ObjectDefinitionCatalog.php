<?php
namespace Api\Objects;

use AccountSettingsManagerFactory;
use sfContext;

/**
 * Catalog for retrieving the definition of an object. This is the main entry point for client code wanting to reflect
 * into the objects available in the API.
 *
 * Class ObjectDefinitionCatalog
 * @package Api\Objects
 */
class ObjectDefinitionCatalog
{
	/** @var array $objectDefinitionCache */
	private array $objectDefinitionCache;

	private AccountSettingsManagerFactory $accountSettingsManagerFactory;
	private StaticObjectDefinitionCatalog $staticCatalog;

	public function __construct(
		AccountSettingsManagerFactory $accountSettingsManagerFactory,
		StaticObjectDefinitionCatalog $staticCatalog
	) {
		$this->accountSettingsManagerFactory = $accountSettingsManagerFactory;
		$this->staticCatalog = $staticCatalog;
		$this->objectDefinitionCache = [];
	}

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $objectName
	 * @return bool|ObjectDefinition
	 */
	public function findObjectDefinitionByObjectType(int $version, int $accountId, string $objectName)
	{
		$lowerObjectName = strtolower($objectName);
		if (isset($this->objectDefinitionCache[$version][$accountId][$lowerObjectName])) {
			return $this->objectDefinitionCache[$version][$accountId][$lowerObjectName];
		}

		$staticObjectDefinition = $this->staticCatalog->findObjectDefinitionByObjectType($objectName);
		if (!$staticObjectDefinition) {
			return false;
		}

		$accountSettingsManager = $this->accountSettingsManagerFactory->getInstance($accountId);
		$this->objectDefinitionCache[$version][$accountId][$lowerObjectName] = new ObjectDefinitionImpl(
			$version,
			$accountId,
			$staticObjectDefinition,
			$accountSettingsManager
		);

		return $this->objectDefinitionCache[$version][$accountId][$lowerObjectName];
	}

	/**
	 * @return ObjectDefinitionCatalog
	 * @throws \Exception
	 */
	public static function getInstance(): ObjectDefinitionCatalog
	{
		return sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');
	}
}

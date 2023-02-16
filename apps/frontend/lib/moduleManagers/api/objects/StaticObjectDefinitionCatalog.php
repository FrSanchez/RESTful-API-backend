<?php
namespace Api\Objects;

/**
 * Catalog for retrieving the definition of an object. This is the main entry point for client code wanting to reflect
 * into the objects available in the API.
 *
 * Class StaticObjectDefinitionCatalog
 * @package Api\Objects
 */
abstract class StaticObjectDefinitionCatalog
{
	/** @var StaticObjectDefinitionCatalog */
	private static $INSTANCE;

	/**
	 * @param string $objectName
	 * @return bool|StaticObjectDefinition
	 */
	public abstract function findObjectDefinitionByObjectType(string $objectName);

	/**
	 * @param string $objectName
	 * @return bool|StaticObjectDefinition
	 */
	public abstract function findObjectDefinitionByUrlObjectType(string $objectName);

	/**
	 * @return StaticObjectDefinitionCatalog
	 */
	public static function getInstance(): StaticObjectDefinitionCatalog
	{
		if (!self::$INSTANCE) {
			self::$INSTANCE = new FileSystemStaticObjectDefinitionCatalog(
				FileSystemStaticObjectDefinitionCatalog::getDefaultObjectConfigDirectory()
			);
		}

		return self::$INSTANCE;
	}
}

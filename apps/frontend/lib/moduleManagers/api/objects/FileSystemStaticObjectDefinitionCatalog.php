<?php
namespace Api\Objects;

use Api\Export\ExportManager;
use apiTools;
use generalTools;
use RuntimeException;

/**
 * StaticObjectDefinitionCatalog that is backed by directories on the file system. The folders within the directory
 * represent each of the objects, where the type of the object is the folder name. In the directory, there should be a
 * "schema.yaml" file which contains the definition of the object.
 *
 * Class FileSystemStaticObjectDefinitionCatalog
 * @package Api\Objects
 */
class FileSystemStaticObjectDefinitionCatalog extends StaticObjectDefinitionCatalog
{
	const OBJECT_CONFIG_DIR = ['..', 'config', 'objects'];

	/** @var StaticObjectDefinition[] $lowerNameToObjectDefinitions */
	private array $lowerNameToObjectDefinitions;

	/** @var StaticObjectDefinition[] $urlObjectNameToObjectDefinitions */
	private array $urlObjectNameToObjectDefinitions;

	/** @var string $objectNames */
	private $objectNames;

	/**
	 * ObjectDefinitionCatalog constructor.
	 * @param string $objectConfigDir The directory on the file system that contains the object definitions
	 */
	public function __construct(string $objectConfigDir)
	{
		$staticObjectDefinitions = self::loadObjectDefinitionsFromConfigDir($objectConfigDir);

		$this->lowerNameToObjectDefinitions = [];
		$this->objectNames = [];
		$this->urlObjectNameToObjectDefinitions = [];
		foreach ($staticObjectDefinitions as $objectDefinition) {
			if ($objectDefinition->getType() == ExportManager::EXPORT_PROCEDURE) {
				continue;
			}
			// Fail if the name is not unique
			if (array_key_exists(strtolower($objectDefinition->getType()), $this->lowerNameToObjectDefinitions)) {
				throw new RuntimeException("Object name '{$objectDefinition->getType()}' is not unique.");
			}
			$this->lowerNameToObjectDefinitions[strtolower($objectDefinition->getType())] = $objectDefinition;
			$this->objectNames[] = $objectDefinition->getType();

			// Fail if the URL name is not unique
			if (array_key_exists(strtolower($objectDefinition->getUrlObjectName()), $this->urlObjectNameToObjectDefinitions)) {
				throw new RuntimeException("URL object name '{$objectDefinition->getUrlObjectName()}' is not unique.");
			}
			$this->urlObjectNameToObjectDefinitions[strtolower($objectDefinition->getUrlObjectName())] = $objectDefinition;

		}
	}

	/**
	 * Gets the names of the objects registered in the catalog.
	 * @return string[]
	 */
	public function getObjectNames(): array
	{
		return $this->objectNames;
	}

	/**
	 * @param string $objectName
	 * @return bool|StaticObjectDefinition
	 */
	public function findObjectDefinitionByObjectType(string $objectName)
	{
		$lowerKey = strtolower($objectName);
		return $this->lowerNameToObjectDefinitions[$lowerKey] ?? false;
	}

	/**
	 * @param string $objectName
	 * @return bool|StaticObjectDefinition
	 */
	public function findObjectDefinitionByUrlObjectType(string $objectName)
	{
		$lowerKey = strtolower($objectName);
		return $this->urlObjectNameToObjectDefinitions[$lowerKey] ?? false;
	}

	/**
	 * @param string $objectConfigDir The directory on the file system that contains the object definitions
	 * @return StaticObjectDefinition[]
	 */
	private static function loadObjectDefinitionsFromConfigDir(string $objectConfigDir): array
	{
		$objectFolderNames = scandir($objectConfigDir);
		$objectDefinitions = [];

		/** @var string $objectFolderName */
		foreach ($objectFolderNames as $objectFolderName) {
			if ($objectFolderName == '.' || $objectFolderName == '..') {
				continue;
			}

			$singleObjectConfigPath = join(DIRECTORY_SEPARATOR, [$objectConfigDir, $objectFolderName]);
			if (!is_dir($singleObjectConfigPath)) {
				continue;
			}

			if (!preg_match("/^[A-Za-z][A-Za-z0-9]*$/", $objectFolderName)) {
				throw new \RuntimeException("Invalid object name detected: $objectFolderName. Object names must contain only alphanumeric characters and must start with alphabetical character.");
			}

			// get the constant value from the object's folder name
			$objectConstant = apiTools::getObjectIdFromName($objectFolderName);
			if ($objectConstant == generalTools::API) {
				continue;
			}
			if ($objectConstant == -1) {
				throw new \RuntimeException('Unknown object specified: ' . $objectFolderName . '. Unable to find object constant that matches the name.');
			}

			$filePath = SchemaFileStaticObjectDefinition::getDefaultSchemaFilePath($singleObjectConfigPath);

			$objectDefinitions[] = new SchemaFileStaticObjectDefinition(
				$objectFolderName,
				$filePath,
				$objectConstant
			);
		}
		return $objectDefinitions;
	}

	/**
	 * Gets the default object config directory
	 * @return string
	 */
	public static function getDefaultObjectConfigDirectory(): string
	{
		return join(DIRECTORY_SEPARATOR, array_merge([dirname(__FILE__)], self::OBJECT_CONFIG_DIR));
	}
}

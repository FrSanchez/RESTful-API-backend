<?php

namespace Api\Representations;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\DataType;
use Api\DataTypes\DataTypeCatalog;
use Api\DataTypes\EnumDataType;
use Api\DataTypes\MapDataType;
use Api\DataTypes\PolymorphicDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\Yaml\YamlFile;
use Api\Yaml\YamlObject;
use ReflectionException;
use stdClass;
use RuntimeException;
use stringTools;

class YamlFileStaticRepresentationDefinitionCatalog implements StaticRepresentationDefinitionCatalog
{
	public const REPRESENTATION_CONFIG_DIR = ['..', 'config', 'representations'];
	// The export representation files are auto-generated
	public const EXPORT_REPRESENTATION_GEN_DIR = ['..', 'gen', 'representations', 'export'];

	private static YamlFileStaticRepresentationDefinitionCatalog $instance;

	/** @var string[] $configDirs */
	private array $configDirs;

	/** @var StaticRepresentationDefinition[] $lowerNameToRepresentationDefinitionMap */
	private array $lowerNameToRepresentationDefinitionMap = [];

	/** @var YamlFile[]|null $lowerFileNameToYamlFileMap */
	private ?array $lowerFileNameToYamlFileMap = null;

	/**
	 * @param string[] $configDirectories
	 */
	public function __construct(array $configDirectories)
	{
		$this->configDirs = $configDirectories;
	}

	/**
	 */
	public function getRepresentationNames(): array
	{
		$this->ensureLowerFileNameToFileInfoMap();
		$names = [];

		foreach ($this->lowerFileNameToYamlFileMap as $lowercaseName => $yamlFile) {
			$names[] = $yamlFile->getName();
		}
		sort($names);

		return $names;
	}

	/**
	 * @param string $name
	 * @return StaticRepresentationDefinition|false
	 */
	public function findRepresentationDefinitionByName(string $name)
	{
		if (array_key_exists(strtolower($name), $this->lowerNameToRepresentationDefinitionMap)) {
			return $this->lowerNameToRepresentationDefinitionMap[strtolower($name)];
		}

		$representationYamlFile = $this->getYamlFileByName($name);
		if (is_null($representationYamlFile)) {
			return false;
		}

		$representationName = $representationYamlFile->getName();
		$representationProperties = $this->loadPropertiesFromYamlFile($representationName, $representationYamlFile);

		$yamlObject = new YamlObject($representationYamlFile->parseContentsAsObject());
		$suppressDescriptors = $yamlObject->getPropertyAsBoolean('suppressDescriptors');

		$representationDefinition = new StaticRepresentationDefinition(
			$representationName,
			$representationProperties,
			EmptyCustomRepresentationPropertyProvider::getInstance(),
			null,
			$suppressDescriptors
		);
		$this->lowerNameToRepresentationDefinitionMap[strtolower($representationName)] = $representationDefinition;

		return $representationDefinition;
	}

	private function loadPropertiesFromYamlFile(string $representationName, YamlFile $yamlFile): array
	{
		$yamlObject = new YamlObject($yamlFile->parseContentsAsObject());

		$properties = $yamlObject->getPropertyAsObject('properties');

		if (is_null($properties)) {
			return [];
		}

		$representationPropertyDefinitions = [];
		$polyRepresentation = null;
		foreach ($properties->getPropertyNames() as $propertyName) {
			$propertyObject = $properties->getPropertyAsObject($propertyName);
			$oneOf = $propertyObject->getPropertyAsObject('$oneOf');
			if ($oneOf) {
				if ($polyRepresentation) {
					throw new RuntimeException('Only one polymorphic item in each file is allowed');
				}
				$exception = $propertyObject->getPropertyAsObject('discriminatorException');
				$exceptionData = new stdClass();
				if ($exception) {
					$exceptionData->type = $exception->getPropertyAsString('type');
					$excParameters = $exception->getPropertyAsArray('parameters');
					$exceptionData->parameters = [];
					for ($i = 0; $i < $excParameters->count(); $i++) {
						$value = $excParameters->getValueAsString($i);
						if (empty($value)) {
							$value = "null";
						}
						$exceptionData->parameters[] = $value;
					}
				}
				$discriminator = $propertyObject->getRequiredPropertyAsString('discriminator');
				$dataTypes = [];
				foreach ($oneOf->getPropertyNames() as $mapEntry) {
					$entry = $oneOf->getRequiredPropertyAsObject($mapEntry);
					$entryDataType = $this->getItemDataType($entry->getRequiredPropertyAsString('representation'));
					$dataTypes[$mapEntry] = $entryDataType;
				}
				$polyRepresentation = new PolymorphicRepresentation($dataTypes, $discriminator, $exceptionData);
				$dataType = new PolymorphicDataType($polyRepresentation);
			} else {
				$dataTypeName = $propertyObject->getRequiredPropertyAsString('type', "Required property type is not specified in representation: {$representationName}. {$propertyName}. file: {$yamlFile->getFilePath()}");

				if ($dataTypeName === ArrayDataType::NAME) {
					$itemDataType = $this->getItemDataType($propertyObject->getRequiredPropertyAsString('items', "Array data type requires 'items' to be defined: {$representationName}. {$propertyName} file: {$yamlFile->getFilePath()}"));
					$dataType = new ArrayDataType($itemDataType, 0);
				} elseif ($dataTypeName == MapDataType::NAME) {
					$itemDataType = $this->getItemDataType($propertyObject->getRequiredPropertyAsString('items', "Map data type requires 'items' to be defined: {$representationName}. {$propertyName} file: {$yamlFile->getFilePath()}"));
					$dataType = new MapDataType($itemDataType, 0);
				} elseif (strcasecmp($dataTypeName, EnumDataType::NAME) === 0) {
					$enumFieldClass = $propertyObject->getRequiredPropertyAsString(
						'enumFieldClass',
						"Required property enumFieldClass is not specified in field " .
						"{$representationName}.{$propertyName}."
					);

					$dataType = new EnumDataType($enumFieldClass);
				} else {
					if ($propertyObject->hasProperty('items')) {
						throw new RepresentationConfigException("Property {$representationName}.{$propertyName} cannot contain 'items' property. 'items' are only allowed on arrays");
					}

					$dataType = $this->getItemDataType($dataTypeName);
				}
			}
			$required = $propertyObject->getPropertyAsBoolean('required');
			$representationPropertyDefinitions[] = new RepresentationPropertyDefinition(
				$propertyName,
				$dataType,
				true, // assume readable
				true,  // assume writeable
				$required
			);
		}

		return $representationPropertyDefinitions;
	}

	/**
	 * @param string $typeName
	 * @return DataType|RepresentationReferenceDataType
	 */
	private function getItemDataType(string $typeName)
	{
		if ($typeName === 'array') {
			throw new RepresentationConfigException("Arrays within arrays is not supported.");
		}

		// handle primitive data types
		if (DataTypeCatalog::isPrimitiveDataTypeName($typeName)) {
			return DataTypeCatalog::getDataTypeByName($typeName);
		}

		// Assume that all representations end with Representation. Checking if the name is a valid Representation is
		// performed at build (ValidateRepresentationDefinitionSchemaFilesUnitTest) and at runtime when the data type is
		// used.
		if (stringTools::endsWith($typeName, 'Representation')) {
			return new RepresentationReferenceDataType($typeName);
		}

		throw new RepresentationConfigException("Unknown datatype {$typeName}");
	}

	/**
	 * Gets the default representation config directory
	 * @return string[]
	 */
	public static function getDefaultFileConfigDirectories(): array
	{
		return [implode(DIRECTORY_SEPARATOR, array_merge([dirname(__FILE__)], self::REPRESENTATION_CONFIG_DIR)),
			implode(DIRECTORY_SEPARATOR, array_merge([dirname(__FILE__)], self::EXPORT_REPRESENTATION_GEN_DIR))];
	}

	/**
	 * @return YamlFileStaticRepresentationDefinitionCatalog
	 */
	public static function getInstance(): YamlFileStaticRepresentationDefinitionCatalog
	{
		if (!isset(static::$instance)) {
			static::$instance = new static(static::getDefaultFileConfigDirectories());
		}

		return static::$instance;
	}

	/**
	 * @param string $nameWithoutExtension
	 * @return YamlFile|null
	 */
	private function getYamlFileByName(string $nameWithoutExtension): ?YamlFile
	{
		$lowerFileNameToPathMap = $this->ensureLowerFileNameToFileInfoMap();
		if (array_key_exists(strtolower($nameWithoutExtension), $lowerFileNameToPathMap)) {
			return $lowerFileNameToPathMap[strtolower($nameWithoutExtension)];
		}

		return null;
	}

	/**
	 * @return YamlFile[]
	 */
	private function ensureLowerFileNameToFileInfoMap(): array
	{
		if (!is_null($this->lowerFileNameToYamlFileMap)) {
			return $this->lowerFileNameToYamlFileMap;
		}

		$this->lowerFileNameToYamlFileMap = [];

		foreach ($this->configDirs as $dir) {
			$representationConfigFiles = scandir($dir);
			foreach ($representationConfigFiles as $filename) {
				$filepath = implode(DIRECTORY_SEPARATOR, [$dir, $filename]);
				if (!YamlFile::isValidateFile($filename, $filepath)) {
					continue;
				}

				$configYamlFile = new YamlFile($dir, $filename);

				$this->lowerFileNameToYamlFileMap[strtolower($configYamlFile->getName())] = $configYamlFile;
			}
		}

		return $this->lowerFileNameToYamlFileMap;
	}
}

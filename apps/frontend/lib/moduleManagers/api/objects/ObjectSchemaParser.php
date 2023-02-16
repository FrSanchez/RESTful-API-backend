<?php
namespace Api\Objects;

use Api\Authorization\AbilitiesAccessRuleParser;
use Api\Authorization\FeatureFlagsAccessRuleParser;
use Api\DataTypes\ArrayDataType;
use Api\DataTypes\DataTypeCatalog;
use Api\DataTypes\EnumDataType;
use Api\DataTypes\MapDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\Framework\ProductTagInfo;
use Api\Objects\Collections\ObjectItemTypeDefinition;
use Api\Objects\Collections\RepresentationItemTypeDefinition;
use Api\Objects\Collections\ScalarItemTypeDefinition;
use Api\Objects\Collections\StaticCollectionDefinition;
use Api\Objects\Doctrine\EmptyDoctrineDeleteModifier;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Relationships\RelationshipReferenceToDefinition;
use Api\Representations\RepresentationConfigException;
use Api\Yaml\YamlFile;
use Api\Yaml\YamlException;
use Api\Yaml\YamlObject;
use apiTools;
use generalTools;
use RuntimeException;
use stringTools;

class ObjectSchemaParser
{
	/** @var string
	 * Includes number and implements strict lower camel case as defined by the Google Java Style Guide regex validation.
	 *
	 *  1. The first character is lower case.
	 *  2. The following elements are either a single number or a upper case character followed by lower case characters.
	 *
	 * The following cases are valid:
	 *  xmlHttpRequest
	 *  newCustomerId
	 *  innerStopwatch
	 *  supportsIpv6OnIos
	 *  youTubeImporter
	 *  youtubeImporter
	 *
	 * Not allowed:
	 *  Ending in an uppercase character: affine3D
	 *
	 * https://stackoverflow.com/questions/1128305/regex-for-pascalcased-words-aka-camelcased-with-leading-uppercase-letter#47591707
	 */
	const FIELD_FORMAT = "/^[a-z]+((\d)|([A-Z0-9][a-z0-9]+))*$/";
	const FIELDS_NOT_ALLOWED = [
		'archived',
		'is_archived',
		'isArchived',
	];
	const EXPECTED_SCHEMA_PROPERTIES = [
		"binaryAttachment",
		"customFieldProvider",
		"doctrineQueryModifier",
		"doctrineDeleteModifier",
		"doctrineCreateModifier",
		"doctrineUpdateModifier",
		"doctrineTable",
		"urlObjectName",
		"fields",
		"relationships",
		"operations",
		"isArchivable",
		"isSingleton",
		"productTag",
		"collections",
		"customUrlPath"
	];
	const EXPECTED_RELATIONSHIP_PROPERTIES = ["referenceTo", "doctrineName", "bulkDataProcessor", "featureFlags"];
	const EXPECTED_RELATIONSHIP_REFERENCE_PROPERTIES = ["object", "key"];
	const EXPECTED_FIELD_PROPERTIES = [
		"doctrineField",
		"type",
		"derived",
		"filterable",
		"nullable",
		"required",
		"readOnly",
		"writeOnly",
		"preVersion5ExportDefault",
		"preVersion5Field",
		"sortable",
		"bulkDataProcessor",
		"featureFlags",
		"enumFieldClass",
		"items",
		"queryable",
		"filterableByRange"
	];
	const EXPECTED_COLLECTION_PROPERTIES = ["itemType", "bulkDataProcessor", "featureFlags"];
	const ENUM_DIR = self::OBJECT_MODIFIER_CLASS_PATH;
	const EXPECTED_OPERATIONS = ['read','create','update','query','delete','export'];
	const OBJECT_MODIFIER_CLASS_PATH = "\\Api\\Config\\Objects";

	/** @var string $objectName */
	private $objectName;

	/** @var int $objectConstantValue */
	private $objectConstantValue;

	/** @var YamlFile $yamlFile */
	private $yamlFile;

	/** @var YamlObject $yamlObject */
	private $yamlObject;

	/**
	 * ObjectSchemaFileHelper constructor.
	 * @param string $filePath
	 * @param string $objectName
	 * @param int $objectConstantValue
	 * @throws YamlException
	 */
	public function __construct(string $filePath, string $objectName, int $objectConstantValue)
	{
		$fileName = substr($filePath, strrpos($filePath, DIRECTORY_SEPARATOR) + 1);
		$directory = substr($filePath, 0, strlen($fileName) * -1);

		$this->yamlFile = new YamlFile($directory, $fileName);
		$this->yamlObject = new YamlObject($this->yamlFile->parseContentsAsObject());
		$this->objectName = $objectName;
		$this->objectConstantValue = $objectConstantValue;

		$this->checkForInvalidProperties($this->yamlObject->getPropertyNames(), self::EXPECTED_SCHEMA_PROPERTIES);
	}

	/**
	 * @param StaticObjectDefinition $default
	 * @return StaticObjectDefinition
	 * @throws YamlException
	 */
	public function parseFile(StaticObjectDefinition $default): StaticObjectDefinition
	{
		// load the object operations
		$objectOperationDefinitions = $this->loadObjectOperationDefinitions();

		// load the doctrineQueryModifiers
		$doctrineQueryModifierClass = $this->getDoctrineQueryModifierClass();
		$doctrineCreateModifierClass = $this->getDoctrineCreateModifierClass(isset($objectOperationDefinitions['create']));
		$doctrineUpdateModifierClass = $this->getDoctrineUpdateModifierClass(isset($objectOperationDefinitions['update']));
		$doctrineDeleteModifierClass = $this->getDoctrineDeleteModifierClass(isset($objectOperationDefinitions['delete']));

		$binaryAttachment = $this->yamlObject->getPropertyAsBooleanWithDefault('binaryAttachment', false);

		return new StaticObjectDefinitionImpl(
			$default->getType(),
			apiTools::generateUrlNameFromObjectName($default->getType(), $this->isSingleton()),
			$this->yamlFile->getFilePath(),
			$this->objectConstantValue,
			$this->isArchivable(),
			$this->isSingleton(),
			$this->getDoctrineTable(),
			$binaryAttachment,
			$doctrineQueryModifierClass,
			$doctrineCreateModifierClass,
			$doctrineUpdateModifierClass,
			$doctrineDeleteModifierClass,
			$this->getCustomFieldProvider(),
			$objectOperationDefinitions,
			$this->loadFields(),
			$this->loadRelationshipDefinitions(),
			$this->loadProductTag($this->yamlObject),
			$this->loadCollections(),
			$this->loadCustomUrlPath($this->yamlObject)
		);
	}

	/**
	 * @param array $allProperties
	 * @param array $validProperties
	 * @param string|null $exceptionMessage
	 */
	private function checkForInvalidProperties(array $allProperties, array $validProperties, string $exceptionMessage = null): void
	{
		$invalidProperties = array_diff($allProperties, $validProperties);
		if (!empty($invalidProperties)) {
			$unknownProperties = implode(', ', array_values($invalidProperties));
			throw new RuntimeException(($exceptionMessage ?: "Unknown properties in {$this->objectName}.") . " Check that the properties are spelled correctly.\nproperties: {$unknownProperties}\nfile: {$this->yamlFile->getFilePath()}");
		}
	}

	/**
	 * @param string $doctrineTable
	 */
	private function validateDoctrineTable(string $doctrineTable): void
	{
		if (!is_string($doctrineTable)) {
			throw new RuntimeException("The {$this->objectName}.doctrineTable property must be a string.");
		}

		// do some basic modification on name
		if (!stringTools::startsWith($doctrineTable, 'pi') || !stringTools::endsWith($doctrineTable, 'Table')) {
			throw new RuntimeException("The {$this->objectName}.doctrineTable property must be formatted with the prefix 'pi' and a suffix of 'Table'. For example, 'piListxProspectTable' is a valid name.");
		}
	}

	/**
	 * @return string
	 */
	private function getDefaultDoctrineTable(): string
	{
		// default the doctrine table to the standard "pi{Name}Table"
		return "pi{$this->objectName}Table";
	}

	/**
	 * @param string $doctrineModifierClass
	 * @return bool
	 * @throws RuntimeException
	 */
	private function validateLoadedClassNamespace($doctrineModifierClass) : bool
	{
		$basePath = self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName);
		if (!stringTools::startsWith($doctrineModifierClass, $basePath)) {
			return false;
		}
		return true;
	}

	/**
	 * @return string
	 * @throws YamlException
	 */
	private function getDoctrineQueryModifierClass(): string
	{
		$basePath = self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName);
		$doctrineQueryModifierClass = $this->yamlObject->getPropertyAsString('doctrineQueryModifier');
		if (is_null($doctrineQueryModifierClass)) {
			return $basePath . "\\{$this->objectName}DoctrineQueryModifier";
		}
		if (!$this->validateLoadedClassNamespace($doctrineQueryModifierClass)) {
			throw new RuntimeException("doctrineQueryModifier should be placed under namespace " . $basePath);
		}
		return $doctrineQueryModifierClass;
	}

	/**
	 * @param bool $operationEnabled
	 *
	 * @return string|null
	 * @throws RuntimeException
	 */
	private function getDoctrineDeleteModifierClass(bool $operationEnabled): ?string
	{
		if (!$operationEnabled) {
			return null;
		}
		$basePath = self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName);
		$doctrineDeleteModifierClass = $this->yamlObject->getPropertyAsString('doctrineDeleteModifier');
		if (is_null($doctrineDeleteModifierClass)) {
			return EmptyDoctrineDeleteModifier::class;
		}
		if (!$this->validateLoadedClassNamespace($doctrineDeleteModifierClass)) {
			throw new RuntimeException("doctrineDeleteModifier should be placed under namespace " . $basePath);
		}
		return $doctrineDeleteModifierClass;
	}

	/**
	 * @param bool $operationEnabled
	 *
	 * @return string|null
	 * @throws RuntimeException
	 */
	private function getDoctrineCreateModifierClass(bool $operationEnabled): ?string
	{
		if (!$operationEnabled) {
			return null;
		}
		$basePath = self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName);
		$doctrineCreateModifierClass = $this->yamlObject->getRequiredPropertyAsString('doctrineCreateModifier', 'doctrineCreateModifier is required when create operation is enabled');
		if (!$this->validateLoadedClassNamespace($doctrineCreateModifierClass)) {
			throw new RuntimeException("doctrineCreateModifier should be placed under namespace " . $basePath);
		}
		return $doctrineCreateModifierClass;
	}

	/**
	 * @param bool $operationEnabled
	 *
	 * @return string|null
	 * @throws RuntimeException
	 */
	private function getDoctrineUpdateModifierClass(bool $operationEnabled): ?string
	{
		if (!$operationEnabled) {
			return null;
		}

		$basePath = self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName);
		$doctrineUpdateModifierClass = $this->yamlObject->getRequiredPropertyAsString('doctrineUpdateModifier', 'doctrineUpdateModifier is required when update operation is enabled');
		if (!$this->validateLoadedClassNamespace($doctrineUpdateModifierClass)) {
			throw new RuntimeException("doctrineUpdateModifier should be placed under namespace " . $basePath);
		}
		return $doctrineUpdateModifierClass;
	}

	/**
	 * @return CustomFieldProvider
	 * @throws YamlException
	 */
	private function getCustomFieldProvider(): CustomFieldProvider
	{
		$customFieldProviderClass = $this->yamlObject->getPropertyAsString('customFieldProvider');
		if (is_null($customFieldProviderClass)) {
			return EmptyCustomFieldProvider::getInstance();
		}

		if (!class_exists($customFieldProviderClass)) {
			throw new RuntimeException("Unable to find Custom Field Provider specified for {$this->objectName}.");
		}

		$customFieldProvider = new $customFieldProviderClass();
		if (!($customFieldProvider instanceof CustomFieldProvider)) {
			throw new RuntimeException(
				"Unexpected Custom Field Provider specified for {$this->objectName}. It must be an instance of " . CustomFieldProvider::class
			);
		}

		return $customFieldProvider;
	}

	/**
	 * Retrieve whether or not this object is archivable
	 * @return bool
	 * @throws YamlException
	 */
	private function isArchivable(): bool
	{
		return $this->yamlObject->getRequiredPropertyAsBoolean('isArchivable') ?: false;
	}

	/**
	 * Retrieve whether or not this object is a singleton
	 * @return bool
	 * @throws YamlException
	 */
	private function isSingleton(): bool
	{
		return $this->yamlObject->getPropertyAsBooleanWithDefault('isSingleton', false) ?: false;
	}

	/**
	 * @return string
	 * @throws YamlException
	 */
	private function getDoctrineTable(): string
	{
		$doctrineTable = $this->yamlObject->getPropertyAsStringWithDefault('doctrineTable', $this->getDefaultDoctrineTable());
		$this->validateDoctrineTable($doctrineTable);
		return $doctrineTable;
	}

	/**
	 * @return array
	 */
	private function loadObjectOperationDefinitions(): array
	{
		$operations = $this->yamlObject->getPropertyAsObject('operations');

		if (!$operations) {
			return [];
		}

		$this->checkForInvalidProperties($operations->getPropertyNames(), self::EXPECTED_OPERATIONS);

		$abilitiesParser = new AbilitiesAccessRuleParser();

		$lowerObjectOperationDefinitions = [];
		foreach (self::EXPECTED_OPERATIONS as $objectOperationName) {
			$objectOperation = $operations->getPropertyAsObject($objectOperationName);

			if (!$objectOperation) {
				continue;
			}

			$abilityAccessRule = $abilitiesParser->parseFromRequiredYamlProperty($objectOperation);
			$featureFlagParser = new FeatureFlagsAccessRuleParser();
			try {
				$featureFlagAccessRule = $featureFlagParser->parseFromYamlProperty($objectOperation);
			} catch (YamlException $yamlException) {
				throw new RuntimeException("Unable to parse Feature Flags from object configuration. {$yamlException->getMessage()} \n" . $this->yamlFile->getFilePath(), 0, $yamlException);
			}
			$internalOnly = $objectOperation->getPropertyAsBoolean('internalOnly') ?? false;

			$lowerObjectOperationDefinitions[strtolower($objectOperationName)] = new ObjectOperationDefinition(
				$objectOperationName,
				$abilityAccessRule,
				$featureFlagAccessRule,
				$internalOnly
			);
		}

		return $lowerObjectOperationDefinitions;
	}

	/**
	 * @return array
	 * @throws YamlException
	 */
	private function loadFields(): array
	{
		$fields = $this->yamlObject->getPropertyAsObject('fields');
		if (is_null($fields)) {
			return [];
		}

		$fieldDefinitions = [];
		foreach ($fields->getPropertyNames() as $fieldName) {
			if (!preg_match(self::FIELD_FORMAT, $fieldName)) {
				throw new RuntimeException("Invalid field name detected: {$this->objectName}.{$fieldName}. Field names must contain only alphanumeric characters and must start with alphabetical character. Ending in an uppercase character is not supported. \nfile: {$this->yamlFile->getFilePath()}");
			}

			if (in_array($fieldName, self::FIELDS_NOT_ALLOWED)) {
				throw new RuntimeException("Invalid field name: {$this->objectName}.{$fieldName}. 'Archived' is not allowed. Use 'isDeleted' instead.");
			}

			$fieldObject = $fields->getPropertyAsObject($fieldName);
			$dataTypeName = $fieldObject->getRequiredPropertyAsString('type', "Required property type is not specified in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$derived = $fieldObject->getPropertyAsBooleanWithDefault('derived', false, "Invalid value specified for derived property in field {$this->objectName}.{$fieldName}. Must be true or false.\nfile: {$this->yamlFile->getFilePath()}");

			$doctrineField = $fieldObject->getPropertyAsString('doctrineField');
			$preVersion5Field = $fieldObject->getPropertyAsString('preVersion5Field');

			$required = $fieldObject->getPropertyAsBooleanWithDefault('required', false, "Invalid value specified for required property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$readOnly = $fieldObject->getPropertyAsBooleanWithDefault('readOnly', false, "Invalid value specified for readOnly property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$writeOnly = $fieldObject->getPropertyAsBooleanWithDefault('writeOnly', false, "Invalid value specified for writeOnly property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");

			// A Field can never be required and readOnly since it's a contradiction
			if ($required && $readOnly) {
				throw new RuntimeException(
					"Invalid value specified for readOnly property in field {$this->objectName}.{$fieldName}. A field that is required cannot be readOnly since it can never be specified in an input body.\nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			//A field cannot set derived and doctrineField
			if ($derived && $doctrineField){
				throw new RuntimeException(
					"{$this->objectName}.{$fieldName} has invalid schema. A field cannot have 'derived' and 'doctrineField' set. Please eliminate one. \nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			//If set, doctrineField must be different from the field name
			if ($doctrineField && $doctrineField === $fieldName){
				throw new RuntimeException(
					"{$this->objectName}.{$fieldName} has invalid schema. If specifying 'doctrineField' property, it must be different from the field name. Please remove 'doctrineField' or rename the field. \nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			$filterable = $fieldObject->getPropertyAsBooleanWithDefault('filterable', false, "Invalid value specified for filterable property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$filterableByRange = null;
			if ($filterable === true) {
				$filterableByRange = $fieldObject->getPropertyAsBooleanWithDefault('filterableByRange', true, "Invalid value specified for filterableByRange property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			} else {
				if ($fieldObject->hasProperty('filterableByRange')) {
					throw new RuntimeException(
						"filterableByRange can only be set if field is also filterable {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
					);
				}
			}
			$sortable = $fieldObject->getPropertyAsBooleanWithDefault('sortable', false, "Invalid value specified for sortable property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$nullable = $fieldObject->getPropertyAsBooleanWithDefault('nullable', false, "Invalid value specified for nullable property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
			$queryable = $fieldObject->getPropertyAsBooleanWithDefault('queryable', true, "Invalid value specified for queryable property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");

			$preVersion5ExportDefault = $fieldObject->getPropertyAsBooleanWithDefault('preVersion5ExportDefault', true);

			$bulkDataProcessorClass = $fieldObject->getPropertyAsString('bulkDataProcessor', "Invalid value specified for bulkDataProcessor property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");

			if (!is_null($bulkDataProcessorClass) && $derived) {
				throw new RuntimeException(
					"A bulk field can not also be a derived field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			if (!is_null($bulkDataProcessorClass) && $bulkDataProcessorClass[0] != '@') {
				$bulkDataProcessor = new $bulkDataProcessorClass();
				if (!($bulkDataProcessor instanceof BulkDataProcessor)) {
					throw new RuntimeException(
						"An bulk field provider class specified must be an instance of " . BulkDataProcessor::class . " {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
					);
				}
			}

			$featureFlagAccessRuleParser = new FeatureFlagsAccessRuleParser();
			try {
				$featureFlagAccessRule = $featureFlagAccessRuleParser->parseFromYamlProperty(
					$fieldObject,
					'featureFlags',
					true
				);
			} catch (YamlException $e) {
				throw new RuntimeException(
					"Invalid value specified for featureFlags property in field {$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			$this->checkForInvalidProperties(
				$fieldObject->getPropertyNames(),
				self::EXPECTED_FIELD_PROPERTIES,
				"Unknown properties in field {$this->objectName}.{$fieldName}."
			);

			try {
				if (strcasecmp($dataTypeName, EnumDataType::NAME) === 0) {
					$enumFieldClass = $fieldObject->getRequiredPropertyAsString('enumFieldClass',
							"Required property enumFieldClass is not specified in field " .
							"{$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}");
					if (!$this->validateLoadedClassNamespace($enumFieldClass)){
						throw new RuntimeException("enumFieldClass should be placed under namespace " . self::OBJECT_MODIFIER_CLASS_PATH . "\\" . self::getObjectNameForNamespace($this->objectName));
					}
					$dataType = new EnumDataType($enumFieldClass);
				} elseif (strcasecmp($dataTypeName, ArrayDataType::NAME) === 0) {
					$fieldObject->getPropertyAsString('bulkDataProcessor',
						"fields of type array must be bulk" .
						"{$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
					);

					$itemDataType = $fieldObject->getRequiredPropertyAsString('items',
						"Required array datatype property 'items' is not specified in field " .
						"{$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
					);

					if (stringTools::endsWith($itemDataType, 'Representation')) {
						$representationReference = new RepresentationReferenceDataType($itemDataType);
						$dataType = new ArrayDataType($representationReference, 0);
					} else {
						$dataType = new ArrayDataType(DataTypeCatalog::getDataTypeByName($itemDataType), 0);
					}
				} elseif (strcasecmp($dataTypeName, MapDataType::NAME) === 0) {
					if (!$fieldObject->getPropertyAsBoolean('writeOnly')) {
						$fieldObject->getPropertyAsString('bulkDataProcessor',
							"fields of type map that allow read must be bulk" .
							"{$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
						);
					}
					$itemDataType = $fieldObject->getRequiredPropertyAsString('items',
						"Required array datatype property 'items' is not specified in field " .
						"{$this->objectName}.{$fieldName}.\nfile: {$this->yamlFile->getFilePath()}"
					);

					if (stringTools::endsWith($itemDataType, 'Representation')) {
						$representationReference = new RepresentationReferenceDataType($itemDataType);
						$dataType = new MapDataType($representationReference, 0);
					} else {
						$dataType = new MapDataType(DataTypeCatalog::getDataTypeByName($itemDataType), 0);
					}
				}
				else {
					if (stringTools::endsWith($dataTypeName, 'Representation')) {
						$dataType = new RepresentationReferenceDataType($dataTypeName);
					} else {
						$dataType = DataTypeCatalog::getDataTypeByName($dataTypeName);
					}
				}
			} catch (RuntimeException $exception) {
				throw new RuntimeException("Invalid value specified for type property in field {$this->objectName}.{$fieldName}. The value must be the name of a valid data type.\nfile: {$this->yamlFile->getFilePath()}\n{$exception->getMessage()}");
			}

			$fieldDefinitions[] = StaticFieldDefinitionBuilder::create()
				->withName($fieldName, $preVersion5Field)
				->withDataType($dataType)
				->withDoctrineField($doctrineField)
				->withDerived($derived)
				->withFilterable($filterable)
				->withRequired($required)
				->withReadOnly($readOnly)
				->withWriteOnly($writeOnly)
				->withSortable($sortable)
				->withNullable($nullable)
				->withPreVersion5ExportDefault($preVersion5ExportDefault)
				->withBulkDataProcessorClass($bulkDataProcessorClass)
				->withFeatureFlagAccessRule($featureFlagAccessRule)
				->withQueryable($queryable)
				->withFilterableByRange($filterableByRange)
				->build();
		}

		return $fieldDefinitions;
	}

	/**
	 * @return array
	 * @throws YamlException
	 */
	private function loadRelationshipDefinitions(): array
	{
		$relationships = $this->yamlObject->getPropertyAsObject('relationships');
		if (is_null($relationships)) {
			return [];
		}

		$relationshipNameToObjectRelationshipDefinition = [];
		$failureMessageFilePath = "\nfile: {$this->yamlFile->getFilePath()}";
		foreach ($relationships->getPropertyNames() as $relationshipName) {
			$relationshipObject = $relationships->getPropertyAsObject($relationshipName);

			$this->checkForInvalidProperties(
				$relationshipObject->getPropertyNames(),
				self::EXPECTED_RELATIONSHIP_PROPERTIES,
				"Unknown properties in relationships {$this->objectName}.relationship.{$relationshipName}.{$failureMessageFilePath}"
			);

			$referenceToDefinition = $this->loadRelationshipDefinitionReferenceToObject($relationshipObject, $relationshipName, $failureMessageFilePath);
			$doctrineName = $relationshipObject->getPropertyAsString('doctrineName', "Property of doctrineName not of type string.{$failureMessageFilePath}");
			if (is_null($doctrineName)) {
				$doctrineName = 'pi' . ucfirst($relationshipName);
			}

			$bulkDataProcessorClass = $relationshipObject->getPropertyAsString("bulkDataProcessor");

			if (!is_null($bulkDataProcessorClass) && $bulkDataProcessorClass[0] != '@') {
				$bulkDataProcessor = new $bulkDataProcessorClass();
				if (!($bulkDataProcessor instanceof BulkDataProcessor)) {
					throw new RuntimeException("Relationship defined a bulkDataProcessor that is not an instance of " . BulkDataProcessor::class . ".{$failureMessageFilePath}");
				}
			}

			$featureFlagAccessRuleParser = new FeatureFlagsAccessRuleParser();
			try {
				$featureFlagAccessRule = $featureFlagAccessRuleParser
					->parseFromYamlProperty($relationshipObject, 'featureFlags', true);
			} catch (YamlException $e) {
				throw new RuntimeException(
					"Invalid value specified for featureFlags property in field {$this->objectName}.{$relationshipObject}.\nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			$relationshipNameToObjectRelationshipDefinition[$relationshipName] = RelationshipDefinitionBuilder::create()
				->withName($relationshipName)
				->withDoctrineName($doctrineName)
				->withBulkDataProcessor($bulkDataProcessorClass)
				->withRelationshipReferenceToDefinition($referenceToDefinition)
				->withFeatureFlagAccessRule($featureFlagAccessRule)
				->build();
		}

		return $relationshipNameToObjectRelationshipDefinition;
	}

	/**
	 * @param YamlObject $relationshipObject
	 * @param string $relationshipName
	 * @param string $failureMessageFilePath
	 * @return RelationshipReferenceToDefinition
	 */
	private function loadRelationshipDefinitionReferenceToObject(YamlObject $relationshipObject, string $relationshipName, string $failureMessageFilePath): RelationshipReferenceToDefinition
	{
		$referenceToObject = $relationshipObject->getRequiredPropertyAsObject('referenceTo', "Required property of referenceTo is not specified or is not of type yaml object.{$failureMessageFilePath}");

		$this->checkForInvalidProperties(
			$referenceToObject->getPropertyNames(),
			self::EXPECTED_RELATIONSHIP_REFERENCE_PROPERTIES,
			"Unknown properties in relationships reference to {$this->objectName}.relationship.{$relationshipName}.referenceTo.{$failureMessageFilePath}"
		);

		$relationTableObjectName = $referenceToObject->getRequiredPropertyAsString('object', "Required property of object is not specified or is not of type string.{$failureMessageFilePath}");
		$relationTableObjectKey = $referenceToObject->getRequiredPropertyAsString('key', "Required property of key is not specified or is not of type string.{$failureMessageFilePath}");

		return new RelationshipReferenceToDefinition($relationTableObjectName, $relationTableObjectKey);
	}

	private function loadProductTag(
		YamlObject $yamlObject
	): ProductTagInfo {
		$productTagName = $yamlObject->getRequiredPropertyAsString('productTag', "Required property 'productTag' is not specified or is not of type string.");
		return new ProductTagInfo($productTagName);
	}

	/**
	 * Gets the name used for an object when the object's name is found in a PHP namespace. PHP doesn't allow
	 * reserved keywords in the namespace so this method ensures that the name is safe.
	 *
	 * This method does not verify that the name specified is valid or correct.
	 *
	 * @param string $objectName
	 * @return string
	 */
	public static function getObjectNameForNamespace(string $objectName): string
	{
		$cleanName = generalTools::translateToUpperCamelCase($objectName, '_');
		if ($cleanName === 'List') {
			// PHP doesn't allow "list" to be used in the namespace
			return 'Listx';
		}
		return $cleanName;
	}

	/**
	 * @return StaticCollectionDefinition[]
	 */
	private function loadCollections(): array
	{
		$yamlCollectionsObject = $this->yamlObject->getPropertyAsObject('collections');
		if (is_null($yamlCollectionsObject)) {
			return [];
		}

		$staticCollectionDefinition = [];
		foreach ($yamlCollectionsObject->getPropertyNames() as $collectionName) {
			$yamlCollectionObject = $yamlCollectionsObject->getPropertyAsObject($collectionName);

			$this->checkForInvalidProperties(
				$yamlCollectionObject->getPropertyNames(),
				self::EXPECTED_COLLECTION_PROPERTIES,
				"Unknown properties in collections {$this->objectName}.collections.{$collectionName}. \n{$this->yamlFile->getFilePath()}"
			);

			$bulkDataProcessorClass = $yamlCollectionObject->getRequiredPropertyAsString("bulkDataProcessor", "bulkDataProcessor is required for collections. {$collectionName} \n{$this->yamlFile->getFilePath()}");
			if (!is_null($bulkDataProcessorClass) && $bulkDataProcessorClass[0] != '@') {
				$bulkDataProcessor = new $bulkDataProcessorClass();
				if (!($bulkDataProcessor instanceof BulkDataProcessor)) {
					throw new RuntimeException("Collection defined a bulkDataProcessor that is not an instance of " . BulkDataProcessor::class . ".{$collectionName}. \n{$this->yamlFile->getFilePath()}");
				}
			}

			if (!$yamlCollectionObject->hasProperty('itemType')) {
				throw new RuntimeException("Collection did not define an itemType. {$collectionName} \n{$this->yamlFile->getFilePath()}");
			}

			if ($yamlCollectionObject->isStringPropertyValue('itemType')) {
				$yamlItemTypeString = $yamlCollectionObject->getPropertyAsString('itemType');
				try {
					$itemType = new ScalarItemTypeDefinition(DataTypeCatalog::getDataTypeByName($yamlItemTypeString));
				} catch (RuntimeException $exception) {
					throw new RuntimeException(
						"Unknown type specified for itemType property within collection '{$collectionName}': {$yamlItemTypeString}\n{$this->yamlFile->getFilePath()}",
						0,
						$exception
					);
				}
			} else {
				try {
					$yamlItemTypeObject = $yamlCollectionObject->getPropertyAsObject('itemType');

					$representationName = $yamlItemTypeObject->getPropertyAsString('representationName');
					$objectType = $yamlItemTypeObject->getPropertyAsString('objectType');

					if (!is_null($objectType) && !is_null($representationName)) {
						throw new RuntimeException(
							"Expected either 'objectType' or 'representationName' to be defined and not both: '{$collectionName}'\n{$this->yamlFile->getFilePath()}"
						);
					}

					if (!is_null($objectType)) {
						$itemType = new ObjectItemTypeDefinition($objectType);
					} elseif (!is_null($representationName)) {
						$itemType = new RepresentationItemTypeDefinition($representationName);
					} else {
						throw new RuntimeException(
							"Expected either 'objectType' or 'representationName' to be defined: '{$collectionName}'\n{$this->yamlFile->getFilePath()}"
						);
					}
				} catch (RuntimeException $exception) {
					throw new RuntimeException(
						$exception->getMessage() . "\n Expected itemType property within collection '{$collectionName}' to be a string or an object",
						0,
						$exception
					);
				}
			}

			$featureFlagAccessRuleParser = new FeatureFlagsAccessRuleParser();
			try {
				$featureFlagAccessRule = $featureFlagAccessRuleParser->parseFromYamlProperty(
					$yamlCollectionObject,
					'featureFlags',
					true
				);
			} catch (YamlException $e) {
				throw new RuntimeException(
					"Invalid value specified for featureFlags property in field {$this->objectName}.{$collectionName}.\nfile: {$this->yamlFile->getFilePath()}"
				);
			}

			$staticCollectionDefinition[] = new StaticCollectionDefinition(
				$collectionName,
				$itemType,
				$bulkDataProcessorClass,
				$featureFlagAccessRule
			);
		}

		return $staticCollectionDefinition;
	}

	private function loadCustomUrlPath(YamlObject $yamlObject) : ?string
	{
		$customUrlPath = $yamlObject->getPropertyAsStringWithDefault('customUrlPath', "");
		return empty($customUrlPath) ? null : $customUrlPath;
	}
}

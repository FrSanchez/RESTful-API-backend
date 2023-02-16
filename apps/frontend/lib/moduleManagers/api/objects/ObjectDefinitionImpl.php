<?php
namespace Api\Objects;

use Api\Framework\ProductTagInfo;
use Api\Objects\Collections\CollectionDefinition;
use Api\Objects\Collections\StaticCollectionDefinition;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Api\Objects\Doctrine\DoctrineQueryModifier;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipDefinition;
use Doctrine_Table;
use AccountSettingsManager;
use AccountSettingsConstants;
use AlwaysTrueFeatureFlagAccessRule;
use RuntimeException;

class ObjectDefinitionImpl implements ObjectDefinition
{
	private int $version;
	private int $accountId;
	private StaticObjectDefinition $staticObjectDefinition;
	private AccountSettingsManager $accountSettingsManager;

	private ?DoctrineQueryModifier $doctrineQueryModifier = null;
	private ?array $lowerFieldNameToFieldDefinition = null;
	private ?array $lowerRelationshipNameToRelationshipDefinition = null;
	private ?array $relationshipNames;
	private ?array $lowerCollectionNameToCollectionDefinition = null;
	private array $collectionNames = [];

	/**
	 * ObjectDefinitionImpl constructor.
	 * @param int $version
	 * @param int $accountId
	 * @param StaticObjectDefinition $staticObjectDefinition
	 * @param AccountSettingsManager $accountSettingsManager
	 */
	public function __construct(
		int $version,
		int $accountId,
		StaticObjectDefinition $staticObjectDefinition,
		AccountSettingsManager $accountSettingsManager
	) {
		$this->version = $version;
		$this->accountId = $accountId;
		$this->staticObjectDefinition = $staticObjectDefinition;
		$this->accountSettingsManager = $accountSettingsManager;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->staticObjectDefinition->getType();
	}

	/**
	 * @return string
	 */
	public function getUrlObjectName(): string
	{
		return $this->staticObjectDefinition->getUrlObjectName();
	}

	/**
	 * @return int
	 */
	public function getConstantValue(): int
	{
		return $this->staticObjectDefinition->getConstantValue();
	}

	/**
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->staticObjectDefinition->getPath();
	}

	/**
	 * @return DoctrineQueryModifier
	 */
	public function getDoctrineQueryModifier(): DoctrineQueryModifier
	{
		if (!is_null($this->doctrineQueryModifier)) {
			return $this->doctrineQueryModifier;
		}

		$doctrineQueryModifierClass = $this->staticObjectDefinition->getDoctrineQueryModifierClass();
		if (!class_exists($doctrineQueryModifierClass)) {
			$this->doctrineQueryModifier = new DoctrineQueryModifier($this);
			return $this->doctrineQueryModifier;
		}

		$doctrineQueryModifier = new $doctrineQueryModifierClass($this);
		if (!($doctrineQueryModifier instanceof DoctrineQueryModifier)) {
			throw new RuntimeException(
				"Invalid doctrine query modifier. Must be an instance of DoctrineQueryModifier.\nfile: {$this->yamlFile->getFilePath()}"
			);
		}

		$this->doctrineQueryModifier = $doctrineQueryModifier;
		return $this->doctrineQueryModifier;
	}

	/**
	 * @return DoctrineDeleteModifier
	 */
	public function getDoctrineDeleteModifier(): DoctrineDeleteModifier
	{
		return $this->staticObjectDefinition->getDoctrineDeleteModifier();
	}

	/**
	 * @return DoctrineCreateModifier
	 */
	public function getDoctrineCreateModifier(): DoctrineCreateModifier
	{
		return $this->staticObjectDefinition->getDoctrineCreateModifier();
	}

	/**
	 * @return DoctrineUpdateModifier
	 */
	public function getDoctrineUpdateModifier(): DoctrineUpdateModifier
	{
		return $this->staticObjectDefinition->getDoctrineUpdateModifier();
	}

	/**
	 * @return Doctrine_Table
	 */
	public function getDoctrineTable(): Doctrine_Table
	{
		return $this->staticObjectDefinition->getDoctrineTable();
	}

	/**
	 * @param QueryContext $queryContext
	 * @param FieldDefinition[] $selectedFields
	 * @return \Doctrine_Query
	 */
	public function createDoctrineQuery(QueryContext $queryContext, array $selectedFields): \Doctrine_Query
	{
		return $this->getDoctrineQueryModifier()->createDoctrineQuery($queryContext, $selectedFields);
	}

	/**
	 * @return bool
	 */
	public function hasBinaryAttachment(): bool
	{
		return $this->staticObjectDefinition->hasBinaryAttachment();
	}

	/**
	 * @param string $name
	 * @return ObjectOperationDefinition|bool
	 */
	public function getObjectOperationDefinitionByName(string $name)
	{
		$objectOperationDefinition = $this->staticObjectDefinition->getObjectOperationDefinitionByName($name);
		if (!$objectOperationDefinition) {
			return false;
		}

		if (!$this->accountSettingsManager->evaluateFeatureFlagAccessRule($objectOperationDefinition->getFeatureFlags())) {
			return false;
		}

		return $objectOperationDefinition;
	}

	/**
	 * @param string $fieldName
	 * @return FieldDefinition|bool
	 */
	public function getFieldByName(string $fieldName)
	{
		$this->ensureFieldsLoaded();

		$lowerFieldName = strtolower($fieldName);
		return $this->lowerFieldNameToFieldDefinition[$lowerFieldName] ?? false;
	}

	/**
	 * @return FieldDefinition[]
	 */
	public function getFields(): array
	{
		$this->ensureFieldsLoaded();

		if (is_null($this->lowerFieldNameToFieldDefinition) || empty($this->lowerFieldNameToFieldDefinition)) {
			return [];
		}
		return array_values($this->lowerFieldNameToFieldDefinition);
	}

	/**
	 * @param string $fieldName
	 * @return FieldDefinition|bool
	 */
	public function getStandardFieldByName(string $fieldName)
	{
		$staticFieldDefinition = $this->staticObjectDefinition->getFieldByName($fieldName);
		if (!$staticFieldDefinition) {
			return false;
		}

		return new FieldDefinition($this->version, $staticFieldDefinition);
	}

	/**
	 * @return bool
	 */
	public function isArchivable(): bool
	{
		return $this->staticObjectDefinition->isArchivable();
	}

	/**
	 * @return bool
	 */
	public function isSingleton(): bool
	{
		return $this->staticObjectDefinition->isSingleton();
	}

	/**
	 * @return string[]
	 */
	public function getRelationshipNames(): array
	{
		$this->ensureRelationshipsLoaded();
		return $this->relationshipNames;
	}

	/**
	 * @param string $relationshipName
	 * @return RelationshipDefinition|bool
	 */
	public function getRelationshipByName(string $relationshipName)
	{
		$this->ensureRelationshipsLoaded();
		return $this->lowerRelationshipNameToRelationshipDefinition[strtolower($relationshipName)] ?? false;
	}

	/**
	 * @return CustomFieldProvider
	 */
	public function getCustomFieldProvider(): CustomFieldProvider
	{
		return $this->staticObjectDefinition->getCustomFieldProvider();
	}

	public function getProductTag(): ProductTagInfo
	{
		return $this->staticObjectDefinition->getProductTag();
	}

	private function ensureFieldsLoaded(): void
	{
		if (!is_null($this->lowerFieldNameToFieldDefinition)) {
			return;
		}

		$this->loadStandardFields();
		$this->loadCustomFields();
	}

	private function loadStandardFields(): void
	{
		$staticFields = $this->staticObjectDefinition->getFields();
		foreach ($staticFields as $staticField) {
			if (!$this->isFieldEnabled($staticField)) {
				continue;
			}

			$fieldDefinition = new FieldDefinition($this->version, $staticField);
			$lowerFieldName = strtolower($fieldDefinition->getName());
			$this->lowerFieldNameToFieldDefinition[$lowerFieldName] = $fieldDefinition;
		}
	}

	private function loadCustomFields(): void
	{
		if (!$this->isCustomFieldsEnabled()) {
			return;
		}

		$staticCustomFieldDefinitions = $this->getCustomFieldProvider()->getAdditionalFields($this->accountId, $this->version);
		foreach ($staticCustomFieldDefinitions as $staticCustomFieldDefinition) {
			if (!$this->isFieldEnabled($staticCustomFieldDefinition)) {
				continue;
			}

			$customFieldDefinition = new FieldDefinition($this->version, $staticCustomFieldDefinition);
			$lowerFieldName = strtolower($customFieldDefinition->getName());
			$defaultFieldDefinition = $this->lowerFieldNameToFieldDefinition[$lowerFieldName] ?? null;

			if (!$defaultFieldDefinition ||
				($this->version < 5 && !$defaultFieldDefinition->isFieldIncludedInExportDefault())) {
				$this->lowerFieldNameToFieldDefinition[$lowerFieldName] = $customFieldDefinition;
			}
		}
	}

	private function ensureRelationshipsLoaded()
	{
		if (!is_null($this->lowerRelationshipNameToRelationshipDefinition)) {
			return;
		}

		$this->relationshipNames = [];
		$this->lowerRelationshipNameToRelationshipDefinition = [];

		$staticRelationshipNames = $this->staticObjectDefinition->getRelationshipNames();
		foreach ($staticRelationshipNames as $staticRelationshipName) {
			$relationshipDefinition = $this->staticObjectDefinition->getRelationshipByName($staticRelationshipName);
			if (!$this->isRelationshipEnabled($relationshipDefinition)) {
				continue;
			}

			$this->lowerRelationshipNameToRelationshipDefinition[strtolower($staticRelationshipName)] = $relationshipDefinition;
			$this->relationshipNames[] = $relationshipDefinition->getName();
		}

		sort($this->relationshipNames);
	}

	/**
	 * Determine if custom field support is enabled for a given Pardot account
	 * @return bool
	 */
	private function isCustomFieldsEnabled() : bool
	{
		return $this->accountSettingsManager->isFlagEnabled(
			AccountSettingsConstants::FEATURE_ENABLE_FRAMEWORK_FOR_CUSTOM_FIELDS
		);
	}

	/**
	 * @param StaticFieldDefinition $fieldDefinition
	 * @return bool True if the specified field is enabled as a valid field accessible via the API
	 */
	private function isFieldEnabled(StaticFieldDefinition $fieldDefinition)
	{
		$featureFlagAccessRule = $fieldDefinition->getFeatureFlagAccessRule();
		if (empty($featureFlagAccessRule) || $featureFlagAccessRule instanceof AlwaysTrueFeatureFlagAccessRule) {
			return true;
		}

		return $this->accountSettingsManager->evaluateFeatureFlagAccessRule($featureFlagAccessRule);
	}

	/**
	 * @param RelationshipDefinition $relationshipDefinition
	 * @return bool True if the specified field is enabled as a valid field accessible via the API
	 */
	private function isRelationshipEnabled(RelationshipDefinition $relationshipDefinition)
	{
		$featureFlagAccessRule = $relationshipDefinition->getFeatureFlagAccessRule();
		if (empty($featureFlagAccessRule) || $featureFlagAccessRule instanceof AlwaysTrueFeatureFlagAccessRule) {
			return true;
		}

		return $this->accountSettingsManager->evaluateFeatureFlagAccessRule($featureFlagAccessRule);
	}

	/**
	 * Ensure that collections are loaded for the account and version.
	 */
	private function ensureCollectionsLoaded(): void
	{
		if (!is_null($this->lowerCollectionNameToCollectionDefinition)) {
			return;
		}

		$this->loadCollections();
	}

	/**
	 * Load all collection to CollectionDefinitions
	 */
	private function loadCollections(): void
	{
		$staticCollectionNames = $this->staticObjectDefinition->getCollectionNames();
		foreach ($staticCollectionNames as $collectionName) {
			$staticCollectionDefinition = $this->staticObjectDefinition->getCollectionDefinitionByName($collectionName);
			if (!$this->isCollectionEnabled($staticCollectionDefinition)) {
				continue;
			}

			$collectionDefinition = new CollectionDefinition($this->version, $staticCollectionDefinition);
			$lowerCollectionName = strtolower($collectionDefinition->getName());
			$this->lowerCollectionNameToCollectionDefinition[$lowerCollectionName] = $collectionDefinition;
			$this->collectionNames[] = $collectionDefinition->getName();
		}
	}

	/**
	 * @param StaticCollectionDefinition $collectionDefinition
	 * @return bool True if the specified field is enabled as a valid field accessible via the API
	 */
	private function isCollectionEnabled(StaticCollectionDefinition $collectionDefinition)
	{
		$featureFlagAccessRule = $collectionDefinition->getFeatureFlagAccessRules();
		if (empty($featureFlagAccessRule) || $featureFlagAccessRule instanceof AlwaysTrueFeatureFlagAccessRule) {
			return true;
		}

		return $this->accountSettingsManager->evaluateFeatureFlagAccessRule($featureFlagAccessRule);
	}

	/**
	 * @param string $collectionName
	 * @return CollectionDefinition|false
	 */
	public function getCollectionDefinitionByName(string $collectionName)
	{
		$this->ensureCollectionsLoaded();

		$lowerCollectionName = strtolower($collectionName);
		return $this->lowerCollectionNameToCollectionDefinition[$lowerCollectionName] ?? false;
	}

	/**
	 * @return array
	 */
	public function getCollectionNames(): array
	{
		$this->ensureCollectionsLoaded();
		return $this->collectionNames;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	public function getCustomUrlPath(): ?string
	{
		return $this->staticObjectDefinition->getCustomUrlPath();
	}

	public function getStaticCustomFieldDefinitions(): ?array
	{
		$this->ensureFieldsLoaded();
		return $this->lowerFieldNameToFieldDefinition;
	}
}

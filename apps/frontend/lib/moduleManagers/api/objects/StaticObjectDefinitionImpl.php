<?php

namespace Api\Objects;

use Api\Framework\ProductTagInfo;
use Api\Objects\Collections\StaticCollectionDefinition;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Api\Objects\Collections\CollectionDefinition;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Api\Objects\Doctrine\EmptyDoctrineDeleteModifier;
use Api\Objects\Relationships\RelationshipDefinition;
use Doctrine_Table;
use Exception;
use RuntimeException;

class StaticObjectDefinitionImpl implements StaticObjectDefinition
{
	private string $type;
	private string $urlObjectName;
	private string $path;
	private int $constantValue;
	private bool $isArchivable;
	private bool $isSingleton;
	private string $doctrineTableClass;
	private bool $binaryAttachment;

	private string $doctrineQueryModifierClass;
	private ?string $doctrineCreateModifierClass;
	private ?DoctrineCreateModifier $doctrineCreateModifier = null;
	private ?string $doctrineUpdateModifierClass;
	private ?DoctrineUpdateModifier $doctrineUpdateModifier = null;
	private ?string $doctrineDeleteModifierClass;
	private ?DoctrineDeleteModifier $doctrineDeleteModifier = null;
	private CustomFieldProvider $customFieldProvider;

	/** @var ObjectOperationDefinition[] */
	private array $lowerObjectOperationDefinitions;

	private ?array $lowerFieldNameToDefinitions = null;

	/** @var StaticFieldDefinition[] $staticFieldDefinitions */
	private array $staticFieldDefinitions;

	/** @var RelationshipDefinition[] $relationshipNameToObjectRelationshipDefinition */
	private array $relationshipNameToObjectRelationshipDefinition;

	private ProductTagInfo $productTag;

	/** @var StaticCollectionDefinition[] $staticCollectionDefinitions */
	private array $staticCollectionDefinitions;
	private ?array $lowerCollectionNameToDefinitions = null;
	private array $staticCollectionNames = [];
	private $customUrlPath;

	/**
	 * ObjectDefinitionImpl constructor.
	 * @param string $type
	 * @param string $urlObjectName
	 * @param string $path
	 * @param int $constantValue
	 * @param bool $isArchivable
	 * @param bool $isSingleton
	 * @param string $doctrineTableClass
	 * @param bool $binaryAttachment
	 * @param string $doctrineQueryModifierClass
	 * @param string|null $doctrineCreateModifierClass
	 * @param string|null $doctrineUpdateModifierClass
	 * @param string|null $doctrineDeleteModifierClass
	 * @param CustomFieldProvider $customFieldProvider
	 * @param ObjectOperationDefinition[] $lowerObjectOperationDefinitions
	 * @param StaticFieldDefinition[] $staticFieldDefinitions
	 * @param RelationshipDefinition[] $relationshipNameToObjectRelationshipDefinition
	 * @param ProductTagInfo $productTag
	 * @param StaticCollectionDefinition[] $staticCollectionDefinitions
	 */
	public function __construct(
		string              $type,
		string              $urlObjectName,
		string              $path,
		int                 $constantValue,
		bool                $isArchivable,
		bool                $isSingleton,
		string              $doctrineTableClass,
		bool                $binaryAttachment,
		string              $doctrineQueryModifierClass,
		?string             $doctrineCreateModifierClass,
		?string             $doctrineUpdateModifierClass,
		?string             $doctrineDeleteModifierClass,
		CustomFieldProvider $customFieldProvider,
		array               $lowerObjectOperationDefinitions,
		array               $staticFieldDefinitions,
		array               $relationshipNameToObjectRelationshipDefinition,
		ProductTagInfo      $productTag,
		array               $staticCollectionDefinitions,
		?string             $customUrlPath
	)
	{
		$this->type = $type;
		$this->urlObjectName = $urlObjectName;
		$this->path = $path;
		$this->constantValue = $constantValue;
		$this->isArchivable = $isArchivable;
		$this->isSingleton = $isSingleton;
		$this->doctrineTableClass = $doctrineTableClass;
		$this->binaryAttachment = $binaryAttachment;

		$this->doctrineQueryModifierClass = $doctrineQueryModifierClass;
		$this->doctrineCreateModifierClass = $doctrineCreateModifierClass;
		$this->doctrineUpdateModifierClass = $doctrineUpdateModifierClass;
		$this->doctrineDeleteModifierClass = $doctrineDeleteModifierClass;
		$this->customFieldProvider = $customFieldProvider;

		$this->lowerObjectOperationDefinitions = $lowerObjectOperationDefinitions;
		$this->staticFieldDefinitions = $staticFieldDefinitions;
		$this->relationshipNameToObjectRelationshipDefinition = $relationshipNameToObjectRelationshipDefinition;
		$this->productTag = $productTag;

		$this->staticCollectionDefinitions = $staticCollectionDefinitions;
		$this->customUrlPath = $customUrlPath;
	}

	/**
	 * @inheritDoc
	 */
	public function getType() : string
	{
		return $this->type;
	}

	/**
	 * @inheritDoc
	 */
	public function getUrlObjectName() : string
	{
		return $this->urlObjectName;
	}

	/**
	 * @inheritDoc
	 */
	public function getConstantValue() : int
	{
		return $this->constantValue;
	}

	/**
	 * @inheritDoc
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * @inheritDoc
	 */
	public function getDoctrineQueryModifierClass(): ?string
	{
		return $this->doctrineQueryModifierClass;
	}

	/**
	 * @inheritDoc
	 */
	public function getDoctrineCreateModifier(): DoctrineCreateModifier
	{
		if (is_null($this->doctrineCreateModifierClass)) {
			throw new RuntimeException(
				"Unexpected call to DoctrineCreateModifier specified for {$this->type}, which cannot be created."
			);
		}

		if (is_null($this->doctrineCreateModifier)) {
			$doctrineCreateModifierClass = $this->doctrineCreateModifierClass;
			if (!class_exists($doctrineCreateModifierClass)) {
				throw new RuntimeException("Create operation defined but {$doctrineCreateModifierClass} does not exist");
			}

			$this->doctrineCreateModifier = new $doctrineCreateModifierClass();
			if (!($this->doctrineCreateModifier instanceof DoctrineCreateModifier)) {
				throw new RuntimeException(
					"Invalid doctrine create modifier. Must be an instance of DoctrineCreateModifier.\nactual: {$doctrineCreateModifierClass}"
				);
			}
		}
		return $this->doctrineCreateModifier;
	}

	/**
	 * @inheritDoc
	 */
	public function getDoctrineUpdateModifier(): DoctrineUpdateModifier
	{
		if (is_null($this->doctrineUpdateModifierClass)) {
			throw new RuntimeException(
				"Unexpected call to DoctrineUpdateModifier specified for {$this->type}, which cannot be updated."
			);
		}

		if (is_null($this->doctrineUpdateModifier)) {
			$doctrineUpdateModifierClass = $this->doctrineUpdateModifierClass;
			if (!class_exists($doctrineUpdateModifierClass)) {
				throw new RuntimeException("Update operation defined but {$doctrineUpdateModifierClass} does not exist");
			}

			$this->doctrineUpdateModifier = new $doctrineUpdateModifierClass();
			if (!($this->doctrineUpdateModifier instanceof DoctrineUpdateModifier)) {
				throw new RuntimeException(
					"Invalid doctrine update modifier. Must be an instance of DoctrineUpdateModifier.\nactual: {$doctrineUpdateModifierClass}"
				);
			}
		}
		return $this->doctrineUpdateModifier;
	}

	/**
	 * @inheritDoc
	 */
	public function getDoctrineDeleteModifier(): DoctrineDeleteModifier
	{
		if (is_null($this->doctrineDeleteModifierClass)) {
			throw new RuntimeException(
				"Unexpected call to DoctrineDeleteModifier specified for {$this->type}, which cannot be deleted."
			);
		}

		if (is_null($this->doctrineDeleteModifier)) {
			$doctrineDeleteModifierClass = $this->doctrineDeleteModifierClass;
			if (class_exists($doctrineDeleteModifierClass)) {
				$this->doctrineDeleteModifier = new $doctrineDeleteModifierClass();
				if (!($this->doctrineDeleteModifier instanceof DoctrineDeleteModifier)) {
					throw new RuntimeException("Invalid doctrine delete modifier. Must be an instance of DoctrineDeleteModifier.\nactual: {$doctrineDeleteModifierClass}");
				}
			} else {
				$this->doctrineDeleteModifier = EmptyDoctrineDeleteModifier::getInstance();
			}
		}
		return $this->doctrineDeleteModifier;
	}

	/**
	 * @inheritDoc
	 */
	public function getDoctrineTable(): Doctrine_Table
	{
		try {
			$doctrineClass = $this->doctrineTableClass;
			return call_user_func('\\' . $doctrineClass . '::getInstance');
		} catch (Exception $exc) {
			throw new RuntimeException(
				"Unable to instantiate Doctrine table for {$this->type}. Check that the {$doctrineClass} exists and has getInstance static method. If the table is found under a different name, use the 'doctrineTable' property in the objects schema.yaml to override to the correct name.",
				0,
				$exc
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function hasBinaryAttachment(): bool
	{
		return $this->binaryAttachment;
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectOperationDefinitionByName(string $name)
	{
		$lowerName = strtolower($name);
		return $this->lowerObjectOperationDefinitions[$lowerName] ?? false;
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldByName(string $fieldName)
	{
		$this->loadStaticFields();

		$lowerFieldName = strtolower($fieldName);
		return $this->lowerFieldNameToDefinitions[$lowerFieldName] ?? false;
	}

	private function loadStaticFields()
	{
		if (!is_null($this->lowerFieldNameToDefinitions)) {
			return;
		}

		foreach ($this->staticFieldDefinitions as $staticFieldDefinition) {
			$v5FieldName = strtolower($staticFieldDefinition->getName());
			$v3And4FieldName = strtolower($staticFieldDefinition->getNameVersioned(4));

			$this->lowerFieldNameToDefinitions[$v5FieldName] = $staticFieldDefinition;
			$this->lowerFieldNameToDefinitions[$v3And4FieldName] = $staticFieldDefinition;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getFields(): array
	{
		return $this->staticFieldDefinitions;
	}

	/**
	 * @inheritDoc
	 */
	public function isArchivable(): bool
	{
		return $this->isArchivable;
	}

	/**
	 * @inheritDoc
	 */
	public function isSingleton(): bool
	{
		return $this->isSingleton;
	}

	/**
	 * @inheritDoc
	 */
	public function getRelationshipNames(): array
	{
		return array_keys($this->relationshipNameToObjectRelationshipDefinition);
	}

	/**
	 * @inheritDoc
	 */
	public function getRelationshipByName(string $relationshipName)
	{
		return $this->relationshipNameToObjectRelationshipDefinition[$relationshipName] ?? false;
	}

	/**
	 * Retrieve the Custom Field Provider class for the object
	 * @return CustomFieldProvider
	 */
	public function getCustomFieldProvider(): CustomFieldProvider
	{
		return $this->customFieldProvider;
	}

	/**
	 * @return bool
	 */
	public function supportsCustomFields(): bool
	{
		return $this->customFieldProvider && ! $this->customFieldProvider instanceof EmptyCustomFieldProvider;
	}

	/**
	 * Gets the product tag used to assign issue for this object.
	 * @return ProductTagInfo
	 */
	public function getProductTag(): ProductTagInfo
	{
		return $this->productTag;
	}

	private function loadStaticCollections()
	{
		if (!is_null($this->lowerCollectionNameToDefinitions)) {
			return;
		}

		foreach ($this->staticCollectionDefinitions as $staticCollectionDefinition) {
			$collectionName = strtolower($staticCollectionDefinition->getName());
			$this->lowerCollectionNameToDefinitions[$collectionName] = $staticCollectionDefinition;
			$this->staticCollectionNames[] = $staticCollectionDefinition->getName();
		}
	}

	/**
	 * @param string $collectionName
	 * @return StaticCollectionDefinition|false
	 */
	public function getCollectionDefinitionByName(string $collectionName)
	{
		$this->loadStaticCollections();

		$lowerCollectionName = strtolower($collectionName);
		return $this->lowerCollectionNameToDefinitions[$lowerCollectionName] ?? false;
	}

	/**
	 * @return string[]
	 */
	public function getCollectionNames(): array
	{
		$this->loadStaticCollections();
		return $this->staticCollectionNames;
	}

	/**
	 * @return string|null
	 */
	public function getCustomUrlPath(): ?string
	{
		return $this->customUrlPath;
	}
}

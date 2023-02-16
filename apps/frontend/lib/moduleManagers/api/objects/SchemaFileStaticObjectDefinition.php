<?php
namespace Api\Objects;

use Api\Framework\ProductTagInfo;
use Api\Objects\Collections\StaticCollectionDefinition;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Api\Objects\Collections\CollectionDefinition;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Doctrine_Table;
use Api\Yaml\YamlException;

/**
 * StaticObjectDefinition that is backed by a "schema.yaml" file found in the file system.
 *
 * Class SchemaFileStaticObjectDefinition
 * @package Api\Objects
 */
class SchemaFileStaticObjectDefinition implements StaticObjectDefinition
{
	const DEFAULT_FILENAME = 'schema.yaml';

	private string $type;
	private string $path;
	private int $constantValue;
	private string $objectUrlName;
	private ?StaticObjectDefinition $delegate = null;

	/**
	 * ObjectDefinition constructor.
	 * @param string $type The name of the object
	 * @param string $path The path to the "schema.yaml" file.
	 * @param int $constantValue The value from Export/ObjectConstants
	 */
	public function __construct(string $type, string $path, int $constantValue)
	{
		$this->type = $type;
		$this->path = $path;
		$this->constantValue = $constantValue;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getUrlObjectName(): string
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getUrlObjectName();
	}

	/**
	 * @return int
	 */
	public function getConstantValue(): int
	{
		return $this->constantValue;
	}

	/**
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getDoctrineQueryModifierClass(): ?string
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getDoctrineQueryModifierClass();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getDoctrineDeleteModifier(): DoctrineDeleteModifier
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getDoctrineDeleteModifier();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getDoctrineTable(): Doctrine_Table
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getDoctrineTable();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getCustomFieldProvider(): CustomFieldProvider
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getCustomFieldProvider();
	}

	/**
	 * @return bool
	 */
	public function supportsCustomFields(): bool
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->supportsCustomFields();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function hasBinaryAttachment(): bool
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->hasBinaryAttachment();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getDoctrineCreateModifier(): DoctrineCreateModifier
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getDoctrineCreateModifier();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getDoctrineUpdateModifier(): DoctrineUpdateModifier
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getDoctrineUpdateModifier();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getObjectOperationDefinitionByName(string $name)
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getObjectOperationDefinitionByName($name);
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getFieldByName(string $fieldName)
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getFieldByName($fieldName);
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getFields(): array
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getFields();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function isArchivable(): bool
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->isArchivable();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function isSingleton(): bool
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->isSingleton();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getRelationshipNames(): array
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getRelationshipNames();
	}

	/**
	 * @inheritDoc
	 * @throws YamlException
	 */
	public function getRelationshipByName(string $relationshipName)
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getRelationshipByName($relationshipName);
	}

	/**
	 * Gets the product tag used to assign issue for this object.
	 * @return ProductTagInfo
	 */
	public function getProductTag(): ProductTagInfo
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getProductTag();
	}

	/**
	 * @param string $collectionName
	 * @return StaticCollectionDefinition|false
	 */
	public function getCollectionDefinitionByName(string $collectionName)
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getCollectionDefinitionByName($collectionName);
	}

	/**
	 * @return array
	 */
	public function getCollectionNames(): array
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getCollectionNames();
	}

	/**
	 * @throws YamlException
	 */
	private function ensureObjectSchemaLoaded()
	{
		if (!is_null($this->delegate)) {
			return;
		}

		$objectSchemaParser = new ObjectSchemaParser($this->getPath(), $this->getType(), $this->getConstantValue());
		$this->delegate = $objectSchemaParser->parseFile($this);
	}

	/**
	 * @param string $directory
	 * @return string
	 */
	public static function getDefaultSchemaFilePath(string $directory)
	{
		return join(DIRECTORY_SEPARATOR, [$directory, self::DEFAULT_FILENAME]);
	}

	public function getCustomUrlPath(): ?string
	{
		$this->ensureObjectSchemaLoaded();
		return $this->delegate->getCustomUrlPath();
	}
}

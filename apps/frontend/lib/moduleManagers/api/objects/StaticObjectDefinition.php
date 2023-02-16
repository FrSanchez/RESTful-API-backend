<?php
namespace Api\Objects;

use Api\Framework\ProductTagInfo;
use Api\Objects\Collections\CollectionDefinition;
use Api\Objects\Collections\StaticCollectionDefinition;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Api\Objects\Relationships\RelationshipDefinition;
use Doctrine_Table;

/**
 * Definition of an "object" in the API and is used to retrieve type information about the object.
 *
 * An "object" is a specialized representation in the API, in which it is a "noun", backed by a row in the database,
 * is usually creatable, writable, deletable and/or readable. All objects in the API are representations but not all
 * representations are objects.
 *
 * Interface StaticObjectDefinition
 * @package Api\Objects
 */
interface StaticObjectDefinition
{
	/**
	 * @return string
	 */
	public function getType(): string;

	/**
	 * @return string
	 */
	public function getUrlObjectName(): string;

	/**
	 * @return int
	 */
	public function getConstantValue(): int;

	/**
	 * @return string
	 */
	public function getPath(): string;

	/**
	 * Gets the query modifier class name associated to this object.
	 * @return string|null
	 */
	public function getDoctrineQueryModifierClass(): ?string;

	/**
	 * Gets the delete modifier for Doctrine associated to this object.
	 * @return DoctrineDeleteModifier
	 */
	public function getDoctrineDeleteModifier(): DoctrineDeleteModifier;

	/**
	 * Gets the create modifier for Doctrine associated to this object.
	 * @return DoctrineCreateModifier
	 */
	public function getDoctrineCreateModifier(): DoctrineCreateModifier;

	/**
	 * Gets the update modifier for Doctrine associated to this object.
	 * @return DoctrineUpdateModifier
	 */
	public function getDoctrineUpdateModifier(): DoctrineUpdateModifier;

	/**
	 * Gets the Doctrine_Table associated to this ObjectDefinition.
	 * @return Doctrine_Table
	 */
	public function getDoctrineTable(): Doctrine_Table;

	/**
	 * Determines if object is allowed to have to have a binary attachment. In the API, this allows for binary content
	 * and an object record to be received during a create and saved to the DB.
	 * @return bool True when the object is allowed to have binary content attached to an object's record.
	 */
	public function hasBinaryAttachment(): bool;

	/**
	 * @param string $name
	 * @return bool|ObjectOperationDefinition
	 */
	public function getObjectOperationDefinitionByName(string $name);

	/**
	 * @param string $fieldName
	 * @return bool|StaticFieldDefinition
	 */
	public function getFieldByName(string $fieldName);

	/**
	 * All fields for the object
	 * @return StaticFieldDefinition[]
	 */
	public function getFields(): array;

	/**
	 * gets whether objects of this type are archivable.
	 * @return bool
	 */
	public function isArchivable(): bool;

	/**
	 * gets whether objects of this type are singletons.
	 * @return bool
	 */
	public function isSingleton(): bool;

	/**
	 * @return string[]
	 */
	public function getRelationshipNames(): array;

	/**
	 * @param string $relationshipName
	 * @return bool|RelationshipDefinition
	 */
	public function getRelationshipByName(string $relationshipName);

	/**
	 * @return CustomFieldProvider
	 */
	public function getCustomFieldProvider(): CustomFieldProvider;

	/**
	 * @return bool
	 */
	public function supportsCustomFields(): bool;

	/**
	 * Gets the product tag used to assign issue for this object.
	 * @return ProductTagInfo
	 */
	public function getProductTag(): ProductTagInfo;

	/**
	 * Gets the {@see StaticCollectionDefinition} with the specified name or false if the name does not correspond to a
	 * collection.
	 * @param string $collectionName
	 * @return StaticCollectionDefinition|false
	 */
	public function getCollectionDefinitionByName(string $collectionName);

	/**
	 * Gets the names of the collections associated to this object.
	 * @return string[]
	 */
	public function getCollectionNames(): array;

	/**
	 * Gets the value of a custom url path when defined in schema file
	 * @return string|null
	 */
	public function getCustomUrlPath() : ?string;
}

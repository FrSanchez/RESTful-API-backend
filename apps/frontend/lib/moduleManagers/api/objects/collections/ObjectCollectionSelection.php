<?php
namespace Api\Objects\Collections;

use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Query\Selections\ObjectSelection;
use Api\Objects\Relationships\RelationshipSelection;
use RuntimeException;

class ObjectCollectionSelection extends CollectionSelection
{
	private ObjectSelection $objectSelection;

	public function __construct(
		CollectionDefinition $collectionDefinition,
		ObjectSelection $objectSelection
	) {
		parent::__construct($collectionDefinition);
		$this->objectSelection = $objectSelection;

		$itemType = $collectionDefinition->getItemType();
		if (!($itemType instanceof ObjectItemTypeDefinition)) {
			throw new RuntimeException('Unknown itemType specified: ' . get_class($itemType));
		}

		if (strcmp($objectSelection->getObjectDefinition()->getType(), $itemType->getObjectType()) !== 0) {
			throw new RuntimeException("Reference representation definition '{$objectSelection->getObjectDefinition()->getType()}' was different than item type '{$itemType->getObjectType()}'");
		}
	}

	/**
	 * @return ObjectDefinition
	 */
	public function getReferencedObjectDefinition(): ObjectDefinition
	{
		return $this->objectSelection->getObjectDefinition();
	}

	/**
	 * @return FieldDefinition[]
	 * @deprecated Use {@see getFieldSelections} instead.
	 */
	public function getFields(): array
	{
		$fields = [];
		foreach ($this->getFieldSelections() as $fieldSelection) {
			$fields[] = $fieldSelection->getFieldDefinition();
		}
		return $fields;
	}

	/**
	 * @return FieldSelection[]
	 */
	public function getFieldSelections(): array
	{
		return $this->objectSelection->getFieldSelections();
	}

	/**
	 * @return RelationshipSelection[]
	 */
	public function getRelationshipSelections(): array
	{
		return $this->objectSelection->getRelationshipSelections();
	}

	/**
	 * @return CollectionSelection[]
	 */
	public function getCollectionSelections(): array
	{
		return $this->objectSelection->getCollectionSelections();
	}

	/**
	 * @return string
	 */
	public function getBulkDataProcessorClass(): string
	{
		return $this->collectionDefinition->getBulkDataProcessorClass();
	}

	/**
	 * Gets the selections made on the referenced object.
	 * @return ObjectSelection
	 */
	public function getObjectSelection(): ObjectSelection
	{
		return $this->objectSelection;
	}
}

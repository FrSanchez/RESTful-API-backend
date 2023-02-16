<?php
namespace Api\Objects\Query\Selections;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Relationships\RelationshipSelection;

/**
 * Selection related to an object. This could be a range of selections on the object from fields to collections.
 */
class ObjectSelection
{
	private ObjectDefinition $objectDefinition;

	/** @var FieldSelection[] $fieldSelections */
	private array $fieldSelections;

	/** @var RelationshipSelection[] $relationships */
	private array $relationshipSelections;

	/** @var CollectionSelection[] $collections */
	private array $collectionSelections;

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldSelection[] $fieldSelections
	 * @param RelationshipSelection[] $relationshipSelections
	 * @param CollectionSelection[] $collectionSelections
	 */
	public function __construct(
		ObjectDefinition $objectDefinition,
		array $fieldSelections,
		array $relationshipSelections,
		array $collectionSelections
	) {
		$this->objectDefinition = $objectDefinition;
		$this->fieldSelections = $fieldSelections;
		$this->relationshipSelections = $relationshipSelections;
		$this->collectionSelections = $collectionSelections;
	}

	/**
	 * Gets the object this selection is related to.
	 * @return ObjectDefinition
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * Gets the fields that are selected on this object.
	 * @return FieldSelection[]
	 */
	public function getFieldSelections(): array
	{
		return $this->fieldSelections;
	}

	/**
	 * Gets the relationships that are selected on the object.
	 * @return RelationshipSelection[]
	 */
	public function getRelationshipSelections(): array
	{
		return $this->relationshipSelections;
	}

	/**
	 * Gets the collections that are selected on the object.
	 * @return CollectionSelection[]
	 */
	public function getCollectionSelections(): array
	{
		return $this->collectionSelections;
	}

	/**
	 * Determines if the object selection has any items selected.
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return count($this->fieldSelections) === 0 &&
			count($this->relationshipSelections) === 0 &&
			count($this->collectionSelections) === 0;
	}

	/**
	 * Converts the selections to an array of selections. This is primarily used for older code that uses an array instead
	 * of this collection.
	 * @return array
	 */
	public function toArray(): array
	{
		return array_merge(
			$this->fieldSelections,
			$this->relationshipSelections,
			$this->collectionSelections
		);
	}
}

<?php
namespace Api\Objects\Query\Selections;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Relationships\RelationshipSelection;
use RuntimeException;

/**
 * Builder for {@see ObjectSelection} instances
 */
class ObjectSelectionBuilder
{
	private ObjectDefinition $objectDefinition;

	/** @var FieldDefinition[] $fields */
	private array $fields = [];

	/** @var FieldSelection[] $fieldSelections */
	private array $fieldSelections = [];

	/** @var RelationshipSelection[] $relationshipSelections */
	private array $relationshipSelections = [];

	/** @var CollectionSelection[] $collections */
	private array $collectionSelections = [];

	public function __construct(ObjectDefinition $objectDefinition)
	{
		$this->objectDefinition = $objectDefinition;
	}

	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @return $this
	 * @deprecated Use {@see withFieldSelection} instead.
	 */
	public function withField(FieldDefinition $fieldDefinition): self
	{
		$this->withFieldSelection(new FieldScalarSelection($fieldDefinition));
		return $this;
	}

	public function containsField(FieldDefinition $fieldDefinition): bool
	{
		return array_key_exists($fieldDefinition->getName(), $this->fields);
	}

	/**
	 * @return FieldDefinition[]
	 */
	public function getFields(): array
	{
		return array_values($this->fields);
	}

	/**
	 * Adds the given field selection to the set of selections for this object. If the field already has a previous
	 * selection, it's replaced with the new selection.
	 * @param FieldSelection $fieldSelection
	 * @return $this
	 */
	public function withFieldSelection(FieldSelection $fieldSelection): self
	{
		$fieldDefinition = $fieldSelection->getFieldDefinition();
		$this->fields[$fieldDefinition->getName()] = $fieldDefinition;
		$this->fieldSelections[$fieldDefinition->getName()] = $fieldSelection;
		return $this;
	}

	public function getFieldSelections(): array
	{
		return array_values($this->fieldSelections);
	}

	public function withRelationshipSelection(RelationshipSelection $relationshipSelection): self
	{
		$relationshipName = $relationshipSelection->getRelationshipName();
		if (!array_key_exists($relationshipName, $this->relationshipSelections)) {
			$this->relationshipSelections[$relationshipName] = $relationshipSelection;
		} else {
			$this->relationshipSelections[$relationshipName]->combineRelationshipSelections($relationshipSelection);
		}
		return $this;
	}

	/**
	 * @return RelationshipSelection[]
	 */
	public function getRelationshipsSelections(): array
	{
		return array_values($this->relationshipSelections);
	}

	public function withCollectionSelection(CollectionSelection $collectionSelection): self
	{
		$collectionName = $collectionSelection->getCollectionName();
		if (!array_key_exists($collectionName, $this->collectionSelections)) {
			$this->collectionSelections[$collectionName] = $collectionSelection;
		} else {
			throw new RuntimeException("Unable to set collection selection because it's not unique.");
		}
		return $this;
	}

	/**
	 * @return CollectionSelection[]
	 */
	public function getCollectionSelections(): array
	{
		return array_values($this->collectionSelections);
	}

	/**
	 * Determines if the selection is empty.
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return count($this->fieldSelections) === 0 &&
			count($this->relationshipSelections) === 0 &&
			count($this->collectionSelections) === 0;
	}

	/**
	 * Adds the selections from the instance given in the args to this builder.
	 * @param ObjectSelection $selection
	 * @return $this
	 */
	public function append(ObjectSelection $selection): self
	{
		foreach ($selection->getFieldSelections() as $fieldSelection) {
			$this->withFieldSelection($fieldSelection);
		}
		foreach ($selection->getRelationshipSelections() as $relationshipSelection) {
			$this->withRelationshipSelection($relationshipSelection);
		}
		foreach ($selection->getCollectionSelections() as $collectionSelection) {
			$this->withCollectionSelection($collectionSelection);
		}
		return $this;
	}

	/**
	 * Builds a new instance of {@see ObjectSelection}
	 * @return ObjectSelection
	 */
	public function build(): ObjectSelection
	{
		return new ObjectSelection(
			$this->objectDefinition,
			array_values($this->fieldSelections),
			array_values($this->relationshipSelections),
			array_values($this->collectionSelections)
		);
	}
}

<?php

namespace Api\Objects\Relationships;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Doctrine\QueryBuilderJoinCriteriaRelatedFieldEqualsConstant;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Query\Selections\ObjectSelection;
use Api\Objects\Query\Selections\ObjectSelectionBuilder;

class RelationshipSelection
{
	/** The object definition that "owns" this relationship */
	private ObjectDefinition $objectDefinition;
	private RelationshipDefinition $relationship;

	// Since ObjectSelection is immutable but RelationshipSelection isn't, there is a builder used here.
	private ObjectSelectionBuilder $objectSelectionBuilder;

	/**
	 * RelationshipSelection constructor.
	 * @param ObjectDefinition $objectDefinition The object definition that "owns" this relationship.
	 * @param RelationshipDefinition $relationshipDefinition
	 * @param ObjectSelectionBuilder $objectSelectionBuilder
	 */
	public function __construct(
		ObjectDefinition $objectDefinition,
		RelationshipDefinition $relationshipDefinition,
		ObjectSelectionBuilder $objectSelectionBuilder
	) {
		$this->objectDefinition = $objectDefinition;
		$this->relationship = $relationshipDefinition;
		$this->objectSelectionBuilder = $objectSelectionBuilder;
	}

	public function appendFieldSelection(FieldSelection $fieldSelection): void
	{
		$this->objectSelectionBuilder->withFieldSelection($fieldSelection);
	}

	public function getFieldSelections(): array
	{
		return $this->objectSelectionBuilder->getFieldSelections();
	}

	/**
	 * @param RelationshipSelection $relationshipSelection
	 */
	public function appendRelationSelection(RelationshipSelection $relationshipSelection): void
	{
		$this->objectSelectionBuilder->withRelationshipSelection($relationshipSelection);
	}

	/**
	 * @param CollectionSelection $collectionSelection
	 */
	public function appendCollectionSelection(CollectionSelection $collectionSelection): void
	{
		$this->objectSelectionBuilder->withCollectionSelection($collectionSelection);
	}

	/**
	 * @return CollectionSelection[]
	 */
	public function getCollectionSelections(): array
	{
		return $this->objectSelectionBuilder->getCollectionSelections();
	}

	/**
	 * @param RelationshipSelection $relationshipSelection
	 */
	public function combineRelationshipSelections(
		RelationshipSelection $relationshipSelection
	): void {
		foreach ($relationshipSelection->getFieldSelections() as $fieldSelection) {
			$this->appendFieldSelection($fieldSelection);
		}

		foreach ($relationshipSelection->getChildRelationshipSelections() as $relationship) {
			$this->appendRelationSelection($relationship);
		}
	}

	public function getRelationshipName(): string
	{
		return $this->getRelationship()->getName();
	}

	/**
	 * @return RelationshipDefinition
	 */
	public function getRelationship(): RelationshipDefinition
	{
		return $this->relationship;
	}

	/**
	 * @return ObjectDefinition
	 */
	public function getReferencedObjectDefinition(): ObjectDefinition
	{
		return $this->objectSelectionBuilder->getObjectDefinition();
	}

	/**
	 * @return FieldDefinition[]
	 */
	public function getFields(): array
	{
		return $this->objectSelectionBuilder->getFields();
	}

	/**
	 * Determines if this selection already contains the specified field definition.
	 * @param FieldDefinition $fieldDefinition
	 * @return bool
	 */
	public function containsField(FieldDefinition $fieldDefinition): bool
	{
		return $this->objectSelectionBuilder->containsField($fieldDefinition);
	}

	/**
	 * @return RelationshipSelection[]
	 * @deprecated Use {@see getChildRelationshipSelections} instead.
	 */
	public function getChildRelationshipsSelection(): array
	{
		return $this->getChildRelationshipSelections();
	}

	/**
	 * @return RelationshipSelection[]
	 */
	public function getChildRelationshipSelections(): array
	{
		return $this->objectSelectionBuilder->getRelationshipsSelections();
	}

	/**
	 * @param QueryBuilderNode $queryBuilder
	 */
	public function apply(QueryBuilderNode $queryBuilder): void
	{
		if ($this->relationship->isBulkRelationship()) {
			// Skip bulk relationships since they are handled after the primary query is loaded
			$processorClassName = $this->relationship->getBulkDataProcessorClass();
			$processorClassInstance = new $processorClassName();

			$processorClassInstance->modifyPrimaryQueryBuilder($this->objectDefinition, $this, $queryBuilder);
			return;
		}

		$objectDefinition = $this->getReferencedObjectDefinition();
		$relationshipDoctrineName = $this->getRelationship()->getDoctrineName();

		$queryBuilder->addRelationship($relationshipDoctrineName, $objectDefinition->isArchivable() ? new QueryBuilderJoinCriteriaRelatedFieldEqualsConstant("is_archived", 0) : null);
		$relationshipQueryBuilder = $queryBuilder->getRelationshipQueryBuilder($relationshipDoctrineName);

		$doctrineQueryModifier = $objectDefinition->getDoctrineQueryModifier();
		$doctrineQueryModifier->modifyQueryBuilderWithSelections(
			$relationshipQueryBuilder,
			array_merge(
				$this->getFieldSelections(),
				$this->getChildRelationshipSelections()
			)
		);
	}
}

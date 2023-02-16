<?php
namespace Api\Objects\Collections;

use Api\Representations\RepresentationDefinition;
use Api\Representations\RepresentationPropertyDefinition;
use RuntimeException;

class RepresentationCollectionSelection extends CollectionSelection
{
	private RepresentationSelection $representationSelection;

	public function __construct(
		CollectionDefinition $collectionDefinition,
		RepresentationSelection $representationSelection
	) {
		parent::__construct($collectionDefinition);
		$this->representationSelection = $representationSelection;

		$itemType = $collectionDefinition->getItemType();
		if (!($itemType instanceof RepresentationItemTypeDefinition)) {
			throw new RuntimeException('Unknown itemType specified: ' . get_class($itemType));
		}

		if (strcmp($representationSelection->getRepresentationName(), $itemType->getRepresentationName()) !== 0) {
			throw new RuntimeException("Reference representation definition '{$representationSelection->getRepresentationName()}' was different than item type '{$itemType->getRepresentationName()}'");
		}
	}

	/**
	 * @return RepresentationDefinition
	 */
	public function getReferencedRepresentationDefinition(): RepresentationDefinition
	{
		return $this->representationSelection->getRepresentationDefinition();
	}

	/**
	 * @return RepresentationPropertyDefinition[]
	 */
	public function getProperties(): array
	{
		return $this->representationSelection->getProperties();
	}

	/**
	 * @return RepresentationReferenceSelection[]
	 */
	public function getRepresentationReferenceSelections(): array
	{
		return $this->representationSelection->getRepresentationReferenceSelections();
	}

	public function isEmpty(): bool
	{
		return $this->representationSelection->isEmpty();
	}

	/**
	 * @return RepresentationSelection
	 */
	public function getRepresentationSelection(): RepresentationSelection
	{
		return $this->representationSelection;
	}

	/**
	 * @return string
	 */
	public function getBulkDataProcessorClass(): string
	{
		return $this->collectionDefinition->getBulkDataProcessorClass();
	}
}

<?php
namespace Api\Objects\Collections;

use Api\Representations\RepresentationDefinition;
use RuntimeException;

/**
 * Builder for {@see RepresentationCollectionSelection} instances.
 */
class RepresentationCollectionSelectionBuilder
{
	private CollectionDefinition $collectionDefinition;
	private RepresentationDefinition $representationDefinition;
	private RepresentationSelectionBuilder $representationSelectionBuilder;

	public function __construct(CollectionDefinition $collectionDefinition, RepresentationDefinition $representationDefinition)
	{
		$this->collectionDefinition = $collectionDefinition;
		$this->representationDefinition = $representationDefinition;
	}

	/**
	 * Replaces the current object selection with the specified selection. If the selection was not set prior to this
	 * call, then the selection is used. To append the selections, use {@see appendRepresentationSelection}.
	 * @param RepresentationSelection $selection
	 * @return $this
	 */
	public function withRepresentationSelection(RepresentationSelection $selection): self
	{
		if ($selection->getRepresentationName() !== $this->representationDefinition->getName()) {
			throw new RuntimeException("Unable to use representation selections because they are different representations. {$selection->getRepresentationName()} !== {$this->representationDefinition->getName()}");
		}
		$this->representationSelectionBuilder = new RepresentationSelectionBuilder($selection->getRepresentationDefinition());
		$this->representationSelectionBuilder->append($selection);
		return $this;
	}

	/**
	 * Appends the current representation selection with the specified selection. If the selection was not set prior to this
	 * call, then the selection is used. To replace the selections, use {@see withRepresentationSelection}.
	 * @param RepresentationSelection $selection
	 * @return $this
	 */
	public function appendRepresentationSelection(RepresentationSelection $selection): self
	{
		if ($selection->getRepresentationName() !== $this->representationDefinition->getName()) {
			throw new RuntimeException("Unable to use representation selections because they are different representations. {$selection->getRepresentationName()} !== {$this->representationDefinition->getName()}");
		}
		if (!isset($this->representationSelectionBuilder)) {
			$this->representationSelectionBuilder = new RepresentationSelectionBuilder($selection->getRepresentationDefinition());
		}
		$this->representationSelectionBuilder->append($selection);
		return $this;
	}

	/**
	 * Adds the selections from the instance given in the args to this builder.
	 * @param RepresentationCollectionSelection $selection
	 * @return $this
	 */
	public function append(RepresentationCollectionSelection $selection): self
	{
		if ($selection->getCollectionName() !== $this->collectionDefinition->getName()) {
			throw new RuntimeException("Unable to append representation collection selections because they are different collections. {$selection->getCollectionName()} !== {$this->collectionDefinition->getName()}");
		}
		$this->appendRepresentationSelection($selection->getRepresentationSelection());
		return $this;
	}

	public function build(): RepresentationCollectionSelection
	{
		return new RepresentationCollectionSelection(
			$this->collectionDefinition,
			$this->representationSelectionBuilder->build()
		);
	}
}

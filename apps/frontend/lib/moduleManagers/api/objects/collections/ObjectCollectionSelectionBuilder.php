<?php
namespace Api\Objects\Collections;

use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\ObjectSelection;
use Api\Objects\Query\Selections\ObjectSelectionBuilder;
use RuntimeException;

/**
 * Builder for {@see ObjectCollectionSelection} instances
 */
class ObjectCollectionSelectionBuilder
{
	private CollectionDefinition $collectionDefinition;
	private ObjectDefinition $referencedObjectDefinition;
	private ObjectSelectionBuilder $objectSelectionBuilder;

	public function __construct(CollectionDefinition $collectionDefinition, ObjectDefinition $referencedObjectDefinition)
	{
		$this->collectionDefinition = $collectionDefinition;
		$this->referencedObjectDefinition = $referencedObjectDefinition;
	}

	/**
	 * Replaces the current object selection with the specified selection. If the selection was not set prior to this
	 * call, then the selection is used. To append the selections, use {@see appendObjectSelection}.
	 * @param ObjectSelection $selection
	 * @return $this
	 */
	public function withObjectSelection(ObjectSelection $selection): self
	{
		if ($selection->getObjectDefinition()->getType() !== $this->referencedObjectDefinition->getType()) {
			throw new RuntimeException("Unable to use object selections because they are different types. {$selection->getObjectDefinition()->getType()} !== {$this->referencedObjectDefinition->getType()}");
		}
		$this->objectSelectionBuilder = new ObjectSelectionBuilder($this->referencedObjectDefinition);
		$this->objectSelectionBuilder->append($selection);
		return $this;
	}

	/**
	 * Appends the current object selection with the specified selection. If the selection was not set prior to this
	 * call, then the selection is used. To replace the selections, use {@see withObjectSelection}.
	 * @param ObjectSelection $selection
	 * @return $this
	 */
	public function appendObjectSelection(ObjectSelection $selection): self
	{
		if ($selection->getObjectDefinition()->getType() !== $this->referencedObjectDefinition->getType()) {
			throw new RuntimeException("Unable to use object selections because they are different types. {$selection->getObjectDefinition()->getType()} !== {$this->referencedObjectDefinition->getType()}");
		}
		if (!isset($this->objectSelectionBuilder)) {
			$this->objectSelectionBuilder = new ObjectSelectionBuilder($this->referencedObjectDefinition);
		}
		$this->objectSelectionBuilder->append($selection);
		return $this;
	}

	/**
	 * Adds the selections from the instance given in the args to this builder.
	 * @param ObjectCollectionSelection $selection
	 * @return $this
	 */
	public function append(ObjectCollectionSelection $selection): self
	{
		if ($selection->getCollectionName() !== $this->collectionDefinition->getName()) {
			throw new RuntimeException("Unable to append object collection selections because they are different collections. {$selection->getCollectionName()} !== {$this->collectionDefinition->getName()}");
		}
		if ($selection->getReferencedObjectDefinition()->getType() !== $this->referencedObjectDefinition->getType()) {
			throw new RuntimeException("Unable to append object collection selections because they are different types. {$selection->getReferencedObjectDefinition()->getType()} !== {$this->referencedObjectDefinition->getType()}");
		}
		$this->appendObjectSelection($selection->getObjectSelection());
		return $this;
	}

	public function build(): ObjectCollectionSelection
	{
		return new ObjectCollectionSelection(
			$this->collectionDefinition,
			$this->objectSelectionBuilder->build()
		);
	}
}

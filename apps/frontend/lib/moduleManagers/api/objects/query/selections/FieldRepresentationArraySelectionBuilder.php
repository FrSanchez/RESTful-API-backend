<?php
namespace Api\Objects\Query\Selections;

use Api\Objects\Collections\RepresentationSelection;
use Api\Objects\Collections\RepresentationSelectionBuilder;
use Api\Objects\FieldDefinition;
use Api\Representations\RepresentationDefinition;
use RuntimeException;

/**
 * Builder for instances of {@see FieldRepresentationArraySelection}
 */
class FieldRepresentationArraySelectionBuilder
{
	private FieldDefinition $fieldDefinition;
	private RepresentationDefinition $representationDefinition;
	private RepresentationSelectionBuilder $representationSelectionBuilder;

	public function __construct(FieldDefinition $fieldDefinition, RepresentationDefinition $representationDefinition)
	{
		$this->fieldDefinition = $fieldDefinition;
		$this->representationDefinition = $representationDefinition;
	}

	public function getFieldDefinition(): FieldDefinition
	{
		return $this->fieldDefinition;
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
	 * @param FieldRepresentationArraySelection $selection
	 * @return $this
	 */
	public function append(FieldRepresentationArraySelection $selection): self
	{
		if ($selection->getFieldDefinition()->getName() !== $this->fieldDefinition->getName()) {
			throw new RuntimeException("Unable to append representation array selection because they are different fields. {$selection->getFieldDefinition()->getName()} !== {$this->fieldDefinition->getName()}");
		}
		$this->appendRepresentationSelection($selection->getRepresentationSelection());
		return $this;
	}

	public function build(): FieldRepresentationArraySelection
	{
		return new FieldRepresentationArraySelection(
			$this->fieldDefinition,
			$this->representationSelectionBuilder->build()
		);
	}
}

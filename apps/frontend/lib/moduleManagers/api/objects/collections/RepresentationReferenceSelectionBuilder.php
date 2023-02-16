<?php
namespace Api\Objects\Collections;

use Api\Representations\RepresentationDefinition;
use Api\Representations\RepresentationPropertyDefinition;
use RuntimeException;

/**
 * Builder for {@see RepresentationReferenceSelection} instances.
 */
class RepresentationReferenceSelectionBuilder
{
	private RepresentationPropertyDefinition $propertyDefinition;
	private RepresentationDefinition $representationDefinition;
	private RepresentationSelectionBuilder $representationSelectionBuilder;

	public function __construct(
		RepresentationPropertyDefinition $propertyDefinition,
		RepresentationDefinition $referencedRepresentationDefinition
	) {
		$this->propertyDefinition = $propertyDefinition;
		$this->representationDefinition = $referencedRepresentationDefinition;
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
	 * @param RepresentationReferenceSelection $selection
	 * @return $this
	 */
	public function append(RepresentationReferenceSelection $selection): self
	{
		if ($selection->getPropertyName() !== $this->propertyDefinition->getName()) {
			throw new RuntimeException("Unable to append representation reference selection because they are different properties. {$selection->getPropertyName()} !== {$this->propertyDefinition->getName()}");
		}
		$this->appendRepresentationSelection($selection->getRepresentationSelection());
		return $this;
	}

	public function build(): RepresentationReferenceSelection
	{
		return new RepresentationReferenceSelection(
			$this->propertyDefinition,
			$this->representationSelectionBuilder->build()
		);
	}
}

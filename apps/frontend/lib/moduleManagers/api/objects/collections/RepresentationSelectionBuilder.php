<?php
namespace Api\Objects\Collections;

use Api\DataTypes\RepresentationReferenceDataType;
use Api\Representations\RepresentationDefinition;
use Api\Representations\RepresentationPropertyDefinition;
use RuntimeException;

/**
 * Builder for {@see RepresentationSelection} instances.
 */
class RepresentationSelectionBuilder
{
	private RepresentationDefinition $representationDefinition;

	/** @var RepresentationPropertyDefinition[] $propertyDefinition */
	private array $properties = [];

	/** @var RepresentationReferenceSelectionBuilder[] */
	private array $representationReferenceSelections = [];

	public function __construct(RepresentationDefinition $representationDefinition)
	{
		$this->representationDefinition = $representationDefinition;
	}

	/**
	 * @param RepresentationPropertyDefinition $property
	 * @return $this
	 */
	public function withProperty(RepresentationPropertyDefinition $property): self
	{
		if ($property->getDataType() instanceof RepresentationReferenceDataType) {
			throw new RuntimeException('Unexpected property specified. Properties with ' . RepresentationReferenceDataType::class . ' should be specified using ' . RepresentationReferenceSelection::class . '.');
		}

		$this->properties[$property->getName()] = $property;
		return $this;
	}

	/**
	 * Replaces a reference selection with the given selection. If no selection has been made prior to this call, the
	 * selection is used. If you want to combine the selection, see {@see appendRepresentationReferenceSelection}.
	 * @param RepresentationReferenceSelection $representationReferenceSelection
	 * @return $this
	 */
	public function withRepresentationReferenceSelection(RepresentationReferenceSelection $representationReferenceSelection): self
	{
		$propertyName = $representationReferenceSelection->getPropertyName();
		$this->representationReferenceSelections[$propertyName] = new RepresentationReferenceSelectionBuilder(
			$representationReferenceSelection->getPropertyDefinition(),
			$representationReferenceSelection->getRepresentationSelection()->getRepresentationDefinition()
		);
		$this->representationReferenceSelections[$propertyName]->append($representationReferenceSelection);
		return $this;
	}

	/**
	 * Combines the reference selection with the given selection. If no selection has been made prior to this call, the
	 * selection is used. If you want to replace the selection, see {@see withRepresentationReferenceSelection}.
	 * @param RepresentationReferenceSelection $representationReferenceSelection
	 * @return $this
	 */
	public function appendRepresentationReferenceSelection(RepresentationReferenceSelection $representationReferenceSelection): self
	{
		$propertyName = $representationReferenceSelection->getPropertyName();
		if (!array_key_exists($propertyName, $this->representationReferenceSelections)) {
			$this->representationReferenceSelections[$propertyName] = new RepresentationReferenceSelectionBuilder(
				$representationReferenceSelection->getPropertyDefinition(),
				$representationReferenceSelection->getRepresentationSelection()->getRepresentationDefinition()
			);
		}
		$this->representationReferenceSelections[$propertyName]->append($representationReferenceSelection);
		return $this;
	}

	/**
	 * Adds the selections from the instance given in the args to this builder.
	 * @param RepresentationSelection $selection
	 * @return $this
	 */
	public function append(RepresentationSelection $selection): self
	{
		if ($selection->getRepresentationName() !== $this->representationDefinition->getName()) {
			throw new RuntimeException("Unable to append representation selections because they are different representations. {$selection->getRepresentationName()} !== {$this->representationDefinition->getName()}");
		}

		foreach ($selection->getProperties() as $property) {
			$this->withProperty($property);
		}
		foreach ($selection->getRepresentationReferenceSelections() as $representationReferenceSelection) {
			$this->appendRepresentationReferenceSelection($representationReferenceSelection);
		}
		return $this;
	}

	public function build(): RepresentationSelection
	{
		$representationReferenceSelections = [];
		foreach (array_values($this->representationReferenceSelections) as $representationReferenceSelectionBuilder) {
			$representationReferenceSelections[] = $representationReferenceSelectionBuilder->build();
		}

		return new RepresentationSelection(
			$this->representationDefinition,
			array_values($this->properties),
			$representationReferenceSelections
		);
	}

	public function isEmpty(): bool
	{
		return count($this->properties) === 0 &&
			count($this->representationReferenceSelections) === 0;
	}
}

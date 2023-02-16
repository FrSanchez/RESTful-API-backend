<?php
namespace Api\Objects\Collections;

use Api\Representations\RepresentationDefinition;
use Api\Representations\RepresentationPropertyDefinition;

class RepresentationSelection
{
	private RepresentationDefinition $representationDefinition;

	/** @var RepresentationPropertyDefinition[] $properties */
	private array $properties;

	/** @var RepresentationReferenceSelection[] $representationReferenceSelections */
	private array $representationReferenceSelections;

	/**
	 * @param RepresentationDefinition $representationDefinition
	 * @param RepresentationPropertyDefinition[] $properties
	 * @param RepresentationReferenceSelection[] $representationReferenceSelections
	 */
	public function __construct(
		RepresentationDefinition $representationDefinition,
		array $properties,
		array $representationReferenceSelections
	) {
		$this->representationDefinition = $representationDefinition;
		$this->properties = $properties;
		$this->representationReferenceSelections = $representationReferenceSelections;
	}

	/**
	 * @return string
	 */
	public function getRepresentationName(): string
	{
		return $this->representationDefinition->getName();
	}

	/**
	 * @return RepresentationDefinition
	 */
	public function getRepresentationDefinition(): RepresentationDefinition
	{
		return $this->representationDefinition;
	}

	/**
	 * @return RepresentationPropertyDefinition[]
	 */
	public function getProperties(): array
	{
		return $this->properties;
	}

	/**
	 * @return RepresentationReferenceSelection[]
	 */
	public function getRepresentationReferenceSelections(): array
	{
		return $this->representationReferenceSelections;
	}

	/**
	 * Gets an array of the selections made on the Representation.
	 * @return array
	 */
	public function toArray(): array
	{
		return array_merge(
			$this->properties,
			$this->representationReferenceSelections
		);
	}

	public function isEmpty(): bool
	{
		return count($this->properties) === 0 &&
			count($this->representationReferenceSelections) === 0;
	}
}

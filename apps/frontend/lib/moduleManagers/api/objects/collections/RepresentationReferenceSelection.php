<?php
namespace Api\Objects\Collections;

use Api\DataTypes\RepresentationReferenceDataType;
use Api\Representations\RepresentationPropertyDefinition;
use RuntimeException;

/**
 * Selection for a representation property that references another representation.
 *
 * For example, given the representations Car and Engine and the schemas look like the following
 * <code>
 * Car:
 *     properties:
 *         engine: { type: EngineRepresentation }
 * Engine:
 *     properties:
 *         size: { type: integer }
 * </code>
 * This selection on the car would look like the following "engine.size", which would be a
 * {@see RepresentationReferenceSelection} where the property definition is "engine" and the representation
 * selection would be Engine with a property selection of "size".
 */
class RepresentationReferenceSelection
{
	private RepresentationPropertyDefinition $propertyDefinition;
	private RepresentationSelection $representationSelection;

	public function __construct(
		RepresentationPropertyDefinition $propertyDefinition,
		RepresentationSelection $representationSelection
	) {
		if (!$propertyDefinition->getDataType() instanceof RepresentationReferenceDataType) {
			throw new RuntimeException("Expected property to be a " . RepresentationReferenceDataType::class);
		}
		$this->propertyDefinition = $propertyDefinition;
		$this->representationSelection = $representationSelection;
	}

	public function getPropertyName(): string
	{
		return $this->propertyDefinition->getName();
	}

	public function getPropertyDefinition(): RepresentationPropertyDefinition
	{
		return $this->propertyDefinition;
	}

	public function getRepresentationSelection(): RepresentationSelection
	{
		return $this->representationSelection;
	}
}

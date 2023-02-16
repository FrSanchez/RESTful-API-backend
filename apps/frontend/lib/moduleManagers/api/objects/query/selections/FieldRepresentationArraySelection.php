<?php
namespace Api\Objects\Query\Selections;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\Objects\Collections\RepresentationSelection;
use Api\Objects\FieldDefinition;
use Api\Representations\RepresentationDefinition;
use RuntimeException;

/**
 * Selection of an array field that references a representation.
 */
class FieldRepresentationArraySelection implements FieldSelection
{
	private FieldDefinition $fieldDefinition;
	private RepresentationSelection $representationSelection;

	public function __construct(
		FieldDefinition $fieldDefinition,
		RepresentationSelection $representationSelection
	) {
		$dataType = $fieldDefinition->getDataType();
		if (!$dataType instanceof ArrayDataType || !$dataType->getItemDataType() instanceof RepresentationReferenceDataType) {
			throw new RuntimeException("Unexpected field specified. The fields must be of data type " . ArrayDataType::class . " containing representations.");
		}

		$this->fieldDefinition = $fieldDefinition;
		$this->representationSelection = $representationSelection;
	}

	public function getName(): string
	{
		return $this->fieldDefinition->getName();
	}

	public function getFieldDefinition(): FieldDefinition
	{
		return $this->fieldDefinition;
	}

	public function getReferencedRepresentationDefinition(): RepresentationDefinition
	{
		return $this->representationSelection->getRepresentationDefinition();
	}

	public function getRepresentationSelection(): RepresentationSelection
	{
		return $this->representationSelection;
	}
}

<?php
namespace Api\Objects\Query\Selections;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\Objects\FieldDefinition;
use RuntimeException;

/**
 * Selection for a field that is an array of primitive, scalar values, like integer[] or string[].
 */
class FieldScalarArraySelection implements FieldSelection
{
	private FieldDefinition $fieldDefinition;

	public function __construct(FieldDefinition $fieldDefinition) {
		$dataType = $fieldDefinition->getDataType();

		if ($dataType instanceof ArrayDataType && $dataType->getItemDataType() instanceof RepresentationReferenceDataType) {
			throw new RuntimeException("Unexpected field specified. Fields with data type " . ArrayDataType::class . " of representations should be specified using " . FieldRepresentationArraySelection::class . ".");
		} else if (!$dataType instanceof ArrayDataType) {
			throw new RuntimeException("Unexpected field specified. Fields that are not array types must use the " . FieldScalarSelection::class . ".");
		}

		$this->fieldDefinition = $fieldDefinition;
	}

	public function getFieldDefinition(): FieldDefinition
	{
		return $this->fieldDefinition;
	}

	public function getName(): string
	{
		return $this->fieldDefinition->getName();
	}

	public function isBulkField(): bool
	{
		return $this->fieldDefinition->isBulkField();
	}

	public function isCustom(): bool
	{
		return $this->fieldDefinition->isCustom();
	}
}

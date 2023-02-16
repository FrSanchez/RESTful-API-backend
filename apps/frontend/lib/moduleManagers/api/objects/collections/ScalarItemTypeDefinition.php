<?php
namespace Api\Objects\Collections;

use Api\DataTypes\DataType;

/**
 * ItemType Definition that references a primitive, scalar {@see DataType}.
 */
class ScalarItemTypeDefinition implements ItemTypeDefinition
{
	private DataType $dataType;

	public function __construct(DataType $dataType)
	{
		$this->dataType = $dataType;
	}

	public function getDataType(): DataType
	{
		return $this->dataType;
	}
}

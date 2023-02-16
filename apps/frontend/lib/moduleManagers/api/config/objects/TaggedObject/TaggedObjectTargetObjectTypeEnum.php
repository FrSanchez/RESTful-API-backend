<?php
namespace Api\Config\Objects\TaggedObject;

use Api\Objects\Enums\EnumField;
use piTagObjectTable;

class TaggedObjectTargetObjectTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return piTagObjectTable::getSelectOptions();
	}
}

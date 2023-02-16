<?php
namespace Api\Config\Objects\DynamicContent;

use Api\Objects\Enums\EnumField;
use piDynamicContentTable;

class DynamicContentBasedOnEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		 return piDynamicContentTable::getFieldTypes();
	}
}

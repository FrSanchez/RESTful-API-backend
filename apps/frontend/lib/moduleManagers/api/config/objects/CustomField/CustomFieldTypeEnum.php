<?php
namespace Api\Config\Objects\CustomField;

use Api\Objects\Enums\EnumField;
use FormFieldPeer;
use piField;

class CustomFieldTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return FormFieldPeer::getCustomTypesArray(true, piField::OBJECT_TYPE_PROSPECT, true);
	}
}

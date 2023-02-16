<?php
namespace Api\Config\Objects\CustomField;

use Api\Objects\Enums\EnumField;
use FormFieldPeer;

class CustomFieldValuesPrefillEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		$result = FormFieldPeer::getValueTypeOptions(true);
		unset($result[' ']); // remove the value that is not valid

		return $result;
	}
}

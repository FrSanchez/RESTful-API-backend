<?php
namespace Api\Config\Objects\BulkAction;

use Api\Objects\Enums\EnumField;
use BulkActionApiConstants;

class StatusFieldEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return BulkActionApiConstants::getStatusEnumToStatusStringMapping();
	}
}

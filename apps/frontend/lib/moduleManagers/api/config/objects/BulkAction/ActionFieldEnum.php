<?php
namespace Api\Config\Objects\BulkAction;

use Api\Objects\Enums\EnumField;
use BulkActionApiConstants;

class ActionFieldEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return BulkActionApiConstants::getActionEnumToActionNameMapping();
	}
}

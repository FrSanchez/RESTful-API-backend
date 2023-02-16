<?php
namespace Api\Config\Objects\Import;

use Api\Objects\Enums\EnumField;

class ImportOperationTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
			0 => "upsert",
		];
	}
}

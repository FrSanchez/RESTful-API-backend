<?php
namespace Api\Config\Objects\Import;

use Api\Objects\Enums\EnumField;
use Pardot\Constants\ShardDb\Import\OriginConstants;

class ImportOriginTypeEnum implements EnumField
{

	public function getArray(): array
	{
		return [
			OriginConstants::WIZARD => OriginConstants::WIZARD_NAME,
			OriginConstants::API_EXTERNAL => OriginConstants::API_EXTERNAL_NAME,
		];
	}
}

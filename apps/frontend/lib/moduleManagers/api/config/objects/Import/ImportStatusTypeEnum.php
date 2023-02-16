<?php
namespace Api\Config\Objects\Import;

use Api\Objects\Enums\EnumField;
use Pardot\Constants\ShardDb\Import\StatusConstants;

class ImportStatusTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
			StatusConstants::FAILED => 'Failed',
			StatusConstants::WAITING => 'Waiting',
			StatusConstants::PROCESSING => 'Processing',
			StatusConstants::COMPLETE => 'Complete',
			StatusConstants::OPEN => 'Open',
			StatusConstants::CANCELED => 'Cancelled',
			StatusConstants::READY => 'Ready',
		];
	}
}

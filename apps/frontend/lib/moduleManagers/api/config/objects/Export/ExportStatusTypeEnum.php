<?php
namespace Api\Config\Objects\Export;

use Api\Objects\Enums\EnumField;
use Pardot\Constants\ShardDb\Export\StatusConstants;

class ExportStatusTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
			StatusConstants::COMPLETE => 'Complete',
			StatusConstants::FAILED => 'Failed',
			StatusConstants::PROCESSING => 'Processing',
			StatusConstants::WAITING => 'Waiting',
			StatusConstants::CANCELED => 'Canceled'
		];
	}
}

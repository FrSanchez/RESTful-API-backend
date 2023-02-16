<?php
namespace Api\Config\Objects\TrackerDomain;

use Api\Objects\Enums\EnumField;
use piTrackerDomainTable;

class TrackerDomainValidationStatusEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		 return piTrackerDomainTable::getValidationStatusMap();
	}
}

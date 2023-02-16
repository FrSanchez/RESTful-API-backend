<?php
namespace Api\Config\Objects\TrackerDomain;

use Api\Objects\Enums\EnumField;
use Pardot\Constants\GlobalDb\GlobalTrackerDomain\VanityUrlStatusConstants;

class TrackerDomainVanityUrlStatusEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return array_merge(array_flip(VanityUrlStatusConstants::getValuesMap()),
			array(3 => VanityUrlStatusConstants::getNameFromValue(1) . ' ' . VanityUrlStatusConstants::getNameFromValue(2)));
	}
}

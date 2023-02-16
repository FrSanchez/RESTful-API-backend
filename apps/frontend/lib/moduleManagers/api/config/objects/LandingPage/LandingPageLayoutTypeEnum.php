<?php
namespace Api\Config\Objects\LandingPage;

use Api\Objects\Enums\EnumField;
use LandingPagePeer;

class LandingPageLayoutTypeEnum implements EnumField
{
	public function getArray(): array
	{
		return LandingPagePeer::getLayoutTypeNames();
	}
}

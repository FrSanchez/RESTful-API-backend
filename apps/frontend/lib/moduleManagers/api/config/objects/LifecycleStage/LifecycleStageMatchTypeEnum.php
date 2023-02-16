<?php

namespace Api\Config\Objects\LifecycleStage;

use Api\Objects\Enums\EnumField;
use piLifecycleStage;

class LifecycleStageMatchTypeEnum implements EnumField
{

	/**
	 * @inheritDoc
	 */
	public function getArray(): array
	{
		return piLifecycleStage::getMatchTypesArray(false);
	}
}

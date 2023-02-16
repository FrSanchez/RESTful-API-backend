<?php

namespace Api\Config\Objects\EngagementProgram;

use Api\Objects\Enums\EnumField;
use piWorkflowTable;

class EngagementProgramStatusTypeEnum implements EnumField
{

	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
//			piWorkflowTable::UNUSED => 'unused',
			piWorkflowTable::DRAFT => 'draft',
			piWorkflowTable::RUNNING => 'running',
			piWorkflowTable::PAUSED => 'paused',
			piWorkflowTable::STARTING => 'starting',
			piWorkflowTable::SCHEDULED => 'scheduled'
		];
	}
}

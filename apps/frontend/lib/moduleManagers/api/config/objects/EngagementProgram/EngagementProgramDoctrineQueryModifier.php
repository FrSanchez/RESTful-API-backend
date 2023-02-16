<?php

namespace Api\Config\Objects\EngagementProgram;

use Api\Config\Objects\EngagementProgram\Gen\Doctrine\AbstractEngagementProgramDoctrineQueryModifier;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;
use WorkflowConstants;

class EngagementProgramDoctrineQueryModifier extends AbstractEngagementProgramDoctrineQueryModifier
{
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$query = parent::createDoctrineQuery($queryContext, $selections);

		return $query
			->addWhere('type = ?', WorkflowConstants::WORKFLOW_TYPE_NURTURE);
	}
}

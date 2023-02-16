<?php

namespace Api\Config\Objects\ListMembership;

use Abilities;
use Api\Config\Objects\ListMembership\Gen\Doctrine\AbstractListMembershipDoctrineQueryModifier;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;

class ListMembershipDoctrineQueryModifier extends AbstractListMembershipDoctrineQueryModifier
{
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$query = parent::createDoctrineQuery($queryContext, $selections);
		if (!$queryContext->getAccessContext()->getUserAbilities()->hasAbility(Abilities::PROSPECTS_PROSPECTS_VIEWNOTASSIGNED)) {
			$query->innerJoin('v.piProspect p')
				->addWhere('p.user_id = ? ', $queryContext->getAccessContext()->getUserId());
		}
		return $query;
	}
}

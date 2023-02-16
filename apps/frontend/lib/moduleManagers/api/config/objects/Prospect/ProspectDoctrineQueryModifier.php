<?php

namespace Api\Config\Objects\Prospect;

use Abilities;
use Api\Config\Objects\Prospect\Gen\Doctrine\AbstractProspectDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;
use piProspect;
use generalTools;

class ProspectDoctrineQueryModifier extends AbstractProspectDoctrineQueryModifier
{
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$query = parent::createDoctrineQuery($queryContext, $selections);
		if (!$queryContext->getAccessContext()->getUserAbilities()->hasAbility(Abilities::PROSPECTS_PROSPECTS_VIEWNOTASSIGNED)) {
			$query->addWhere('user_id =? ', $queryContext->getAccessContext()->getUserId());
		}
		return $query;
	}

	protected function modifyQueryWithSalesforceCampaignIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('piCampaign', 'crm_fid');
	}

	protected function getValueForSalesforceCampaignIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piProspect $doctrineRecord */
		return $doctrineRecord->getCampaignFID();
	}

	protected function modifyQueryWithRecentInteractionField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot
			->addSelection("last_activity_at")
			->addSelection("opted_out");
	}

	protected function getValueForRecentInteractionField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piProspect $doctrineRecord */
		return generalTools::isOnlineText($doctrineRecord->last_activity_at, $doctrineRecord->opted_out);
	}
}

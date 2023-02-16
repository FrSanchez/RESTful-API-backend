<?php

namespace Api\Config\Objects\Opportunity;

use Api\Config\Objects\Opportunity\Gen\Doctrine\AbstractOpportunityDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;

class OpportunityDoctrineQueryModifier extends AbstractOpportunityDoctrineQueryModifier
{
	const IS_WON = 'is_won';
	const IS_CLOSED = 'is_closed';

	protected function modifyQueryWithStatusField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection(self::IS_CLOSED)
			->addSelection(self::IS_WON);
	}

	protected function getValueForStatusField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->getStatusDescription();
	}
}

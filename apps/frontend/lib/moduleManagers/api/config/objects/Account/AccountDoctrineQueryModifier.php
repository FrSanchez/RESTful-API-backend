<?php

namespace Api\Config\Objects\Account;

use Api\Config\Objects\Account\Gen\Doctrine\AbstractAccountDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use DateTime;
use DateTimeZone;
use Doctrine_Query;
use Doctrine_Record;
use sfContext;
use piUserTable;

class AccountDoctrineQueryModifier extends AbstractAccountDoctrineQueryModifier
{

	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$rootQueryBuilderNode = new QueryBuilderNode();
		$this->modifyQueryBuilderWithSelections($rootQueryBuilderNode, $selections);

		// get the Doctrine_Table instance related to the object.
		$objectDef = $this->getObjectDefinition();
		$doctrineTable = $objectDef->getDoctrineTable();
		$primaryTableAlias = 'v';
		$query = $doctrineTable->createQuery($primaryTableAlias);
		$rootQueryBuilderNode->applyToDoctrineQuery($query, $primaryTableAlias);

		$query->addWhere(SystemColumnNames::ID.'= ?', $queryContext->getAccountId());

		// TODO: Not sure if there a cleaner way to do this? (where piCampaign.account_id = ?),
		// weird that this happens (doesnt raise an error with no relations), might be a way to remove the constraint check
		foreach ($selections as $selection) {
			if ($selection instanceof RelationshipSelection) {
				$query->addWhere('pi' . $selection->getRelationship()->getReferenceToDefinition()->getObjectName() . '.account_id = ?', $queryContext->getAccountId());
			}
		}

		return $query;
	}

//	TODO: TBD - might remove, might not be consistent with UI
	protected function modifyQueryWithAdminIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{

	}

	protected function getValueForAdminIdField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return piUserTable::getInstance()->getFirstAdminUser($doctrineRecord->id)->id;
	}

	protected function modifyQueryWithApiCallsUsedField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{

	}

	protected function getValueForApiCallsUsedField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		$now = new DateTime('now', new DateTimeZone($doctrineRecord->getTimezone()));
		$dailyRequestRateLimiter = sfContext::getInstance()->getContainer()->get('api.rateLimiting.dailyRequestRateLimiter');
		return $dailyRequestRateLimiter->getDailyPublicRequestCount($doctrineRecord->id, $now);
	}

	protected function modifyQueryWithMaximumDailyApiCallsField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{

	}

	protected function getValueForMaximumDailyApiCallsField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return (int)$doctrineRecord->getAccountLimit()->max_api_requests;
	}


	protected function modifyQueryWithLevelField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{

	}

	protected function getValueForLevelField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->getTypeNameForAccount();
	}

}

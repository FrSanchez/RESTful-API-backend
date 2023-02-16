<?php

namespace Api\Config\Objects\Salesforce;

use Api\Config\Objects\Salesforce\Gen\Doctrine\AbstractSalesforceDoctrineQueryModifier as AbstractConnectorDoctrineQueryModifierAlias;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use ConnectorPeer;
use Doctrine_Query;
use SalesforceSyncPauseManager;

class SalesforceDoctrineQueryModifier extends AbstractConnectorDoctrineQueryModifierAlias
{

	public function createDoctrineQuery(QueryContext $queryContext,array $selections): Doctrine_Query
	{
		$rootQueryBuilderNode = new QueryBuilderNode();
		$this->modifyQueryBuilderWithSelections($rootQueryBuilderNode, $selections);

		// get the Doctrine_Table instance related to the object.
		$objectDef = $this->getObjectDefinition();
		$doctrineTable = $objectDef->getDoctrineTable();
		$primaryTableAlias = 'v';
		$query = $doctrineTable->createQuery($primaryTableAlias);
		$rootQueryBuilderNode->applyToDoctrineQuery($query, $primaryTableAlias);

		$query->addWhere('account_id = ?', $queryContext->getAccountId());
		$query->addWhere('connector_category_id = ?', ConnectorPeer::CATEGORY_CRM);
		$query->addWhere('connector_vendor_id = ?', ConnectorPeer::VENDOR_SALESFORCE);
		$query->addWhere('is_archived = ?', false);
		return $query;
	}

	protected function modifyQueryWithIsPausedField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
	}

	protected function getValueForIsPausedField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		$isPausedValue = $doctrineRecord->getMetadataValue(SalesforceSyncPauseManager::METADATA_OBJECT_SYNC_PAUSE_KEY);
		if ($isPausedValue === null || $isPausedValue === '') {
			// connector is unpaused
			return false;
		} else {
			// connector is paused
			return true;
		}
	}
}

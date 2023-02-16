<?php

namespace Api\Config\Objects\Salesforce;

use Api\Gen\Representations\SalesforceRepresentation;
use Api\Objects\ObjectActions\ObjectActionContext;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\SingleResultQuery;
use Exception;
use sfContext;
use ConnectorPeer;
use Symfony\Component\Process\Exception\RuntimeException;
use SalesforceSyncPauseManager;
use piObjectAuditTable;
use ExecutionContextManager;


class SalesforceHelper
{

	protected int $accountId;
	protected int $userId;

	public function __construct($accountId, $userId)
	{
		$this->accountId = $accountId;
		$this->userId = $userId;
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param ObjectDefinitionCatalog $objectDefinitionCatalog
	 * @return SalesforceRepresentation
	 * @throws RuntimeException|Exception
	 */
	public function getSalesforceRepresentation(ObjectActionContext $objectActionContext, ObjectDefinitionCatalog $objectDefinitionCatalog): SalesforceRepresentation
	{
		$objectDefinitionCatalog = $objectDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');

		$objectDefinition = $objectDefinitionCatalog-> findObjectDefinitionByObjectType(
			$objectActionContext->getVersion(),
			$objectActionContext->getAccessContext()->getAccountId(),
			'Salesforce');
		$query = SingleResultQuery::from($objectActionContext->getAccessContext()->getAccountId(), $objectDefinition);
		$query->addSelections(
			$objectDefinition->getFieldByName('name'),
			$objectDefinition->getFieldByName('isPaused'),
			$objectDefinition->getFieldByName('updatedAt')
		);
		$query->addWhereEquals('name', ConnectorPeer::getVendorName(\ConnectorPeer::VENDOR_SALESFORCE));
		$query->addWhereEquals('isVerified', 1);

		$queryContext = new QueryContext($objectActionContext->getAccessContext()->getAccountId(), $objectActionContext->getVersion(), $objectActionContext->getAccessContext());
		$objectQueryManager = $objectQueryManager ?? sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');
		$result = $objectQueryManager->queryOne($queryContext, $query);
		$representation = $result->getRepresentation();
		if (is_null($representation)) {
			throw new RuntimeException('Failed to find salesforce connector');
		}
		return $representation;
	}

	/**
	 * @param $connector
	 * @param $isGoingPaused
	 * @return void
	 */
	public function createSyncPauseConnectorObjectAudit($connector, $isGoingPaused): void
	{
		// 0 = Sync is unpaused
		// 1 = Sync is paused
		$changes = [
			SalesforceSyncPauseManager::METADATA_OBJECT_SYNC_PAUSE_KEY => [
				"f" => $connector->getMetadataValue(SalesforceSyncPauseManager::METADATA_OBJECT_SYNC_PAUSE_KEY),
				"t" => ($isGoingPaused ? '1' : '0')
			]];
		$this->createConnectorObjectAudit($connector->id, $changes);
	}

	/**
	 * @param $connectorId
	 * @param $changes
	 * @return void
	 */
	protected function createConnectorObjectAudit($connectorId, $changes): void
	{
		$piObjAudit = piObjectAuditTable::getInstance()->newObject();
		$piObjAudit->account_id = $this->accountId;
		$piObjAudit->user_id = $this->userId;
		$piObjAudit->object_id = $connectorId;
		$piObjAudit->object_type = 'Connector';
		$piObjAudit->source_type = ExecutionContextManager::getInstance()->getExecutionSource();
		$piObjAudit->source_id = ExecutionContextManager::getInstance()->getExecutionId();
		$piObjAudit->changes = json_encode($changes);
		$piObjAudit->save();

	}
}

<?php

namespace Api\Config\BulkDataProcessors;

use Api\Exceptions\ApiException;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\SystemColumnNames;
use CRMManager;
use SalesforceConnector;

class ProspectSalesforceUrlBulkDataProcessor implements BulkDataProcessor
{
	private RecordIdCollection $recordsToLoaded;

	/**
	 * Cache for this already loaded/processed values
	 */
	private RecordIdValueCollection $loadedRecordIds;

	/**
	 * Stores the mapping of record id to contact and lead fids
	 * @var array $recordIdToSelectedFields
	 */
	private array $recordIdToSelectedFields = [];

	/**
	 * ProspectSalesforceUrlBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoaded = new RecordIdCollection();
		$this->loadedRecordIds = new RecordIdValueCollection();
	}

	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		if (strcasecmp($objectDefinition->getType(), 'Prospect') != 0) {
			throw new ApiException(-1, "Expected objectDefinition to be Prospect");
		}

		$queryBuilderNode
			->addSelection(SystemColumnNames::ID)
			->addSelection(SystemColumnNames::ACCOUNT_ID)
			->addSelection(SystemColumnNames::PROSPECT_CRM_CONTACT_FID)
			->addSelection(SystemColumnNames::PROSPECT_CRM_LEAD_FID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (strcasecmp($objectDefinition->getType(), 'Prospect') != 0) {
			throw new ApiException(-1, "Expected objectDefinition to be Prospect");
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}

		if ($this->loadedRecordIds->containsRecordId($objectDefinition, $recordId)) {
			return;
		}
		$crmContactFid = $doctrineRecord->get(SystemColumnNames::PROSPECT_CRM_CONTACT_FID);
		$crmLeadFid = $doctrineRecord->get(SystemColumnNames::PROSPECT_CRM_LEAD_FID);
		if (!$this->isCrmUrlDefined($crmLeadFid, $crmContactFid)) {
			$this->loadedRecordIds->addRecordIdValue($objectDefinition, $recordId);
			return;
		}
		$this->recordsToLoaded->addRecordId($objectDefinition, $recordId);
		$this->recordIdToSelectedFields[$recordId] = [
			SystemColumnNames::PROSPECT_CRM_CONTACT_FID => $crmContactFid,
			SystemColumnNames::PROSPECT_CRM_LEAD_FID => $crmContactFid,
		];
	}

	/**
	 * @param string|null $crmLeadFid
	 * @param string|null $crmContactFid
	 * @return bool
	 */
	private function isCrmUrlDefined(?string $crmLeadFid, ?string $crmContactFid): bool
	{
		if ($crmLeadFid != null && substr($crmLeadFid, 0, 2) == '[[') {
			return false;
		}

		if ($crmContactFid != null && substr($crmContactFid, 0, 2) == '[[') {
			return false;
		}

		if ($crmLeadFid == null && $crmContactFid == null) {
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if ($this->recordsToLoaded->isEmpty()) {
			return;
		}

		$connector = CRMManager::getCrmConnector($queryContext->getAccountId());
		if (!$connector) {
			$this->addRecordIdValueAsNull();
			$this->resetToLoadValues();
			return;
		}

		$connectorClass = CRMManager::getConnectorClass($connector->getConnectorVendorId());
		if (!$connectorClass) {
			$this->addRecordIdValueAsNull();
			$this->resetToLoadValues();
			return;
		}

		/** @var SalesforceConnector $connectorClass */
		foreach ($this->recordsToLoaded->getObjectDefinitions() as $objectDefinition) {
			foreach ($this->recordsToLoaded->getRecordIdsByObjectDefinition($objectDefinition) as $recordId) {
				$url = $connectorClass::getProspectUrlWithContactAndLeadFids(
					$this->recordIdToSelectedFields[$recordId][SystemColumnNames::PROSPECT_CRM_CONTACT_FID],
					$this->recordIdToSelectedFields[$recordId][SystemColumnNames::PROSPECT_CRM_LEAD_FID],
					$connector,
					$queryContext->getAccountId()
				);
				$this->loadedRecordIds->addRecordIdValue($objectDefinition, $recordId, $url);
			}
		}

		$this->resetToLoadValues();
	}

	private function resetToLoadValues(): void
	{
		$this->recordIdToSelectedFields = [];
		$this->recordsToLoaded->removeAllObjectsAndRecords();
	}

	/**
	 * Adds all of the record to load to the loaded collection and the values will be null
	 */
	private function addRecordIdValueAsNull(): void
	{
		foreach ($this->recordsToLoaded->getObjectDefinitions() as $objectDefinition) {
			foreach ($this->recordsToLoaded->getRecordIdsByObjectDefinition($objectDefinition) as $recordId) {
				$this->loadedRecordIds->addRecordIdValue($objectDefinition, $recordId);
			}
		}
	}

	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion
	): bool {
		if (is_null($doctrineRecord)) {
			return false;
		}

		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($currentRecordId)) {
			return false;
		}

		if (!$this->loadedRecordIds->containsRecordId($objectDefinition, $currentRecordId)) {
			return true;
		}

		/** @var FieldDefinition $selection */
		$dbArray[$selection->getName()] = $this->loadedRecordIds->getRecordIdValueByObjectDefinition(
			$objectDefinition,
			$currentRecordId
		);

		return false;
	}
}

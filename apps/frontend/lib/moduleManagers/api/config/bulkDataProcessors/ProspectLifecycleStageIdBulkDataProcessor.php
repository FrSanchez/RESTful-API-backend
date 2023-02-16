<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\SystemColumnNames;
use piLifecycleStageProspectTable;

class ProspectLifecycleStageIdBulkDataProcessor implements BulkDataProcessor
{

	private RecordIdCollection $recordsToLoad;

	/**
	 * Cache for this already loaded/processed values
	 */
	private RecordIdValueCollection $loadedRecordIds;


	/**
	 * ProspectLifecycleStageIdBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoad = new RecordIdCollection();
		$this->loadedRecordIds = new RecordIdValueCollection();
	}

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode
			->addSelection(SystemColumnNames::ID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
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
		$this->recordsToLoad->addRecordId($objectDefinition, $recordId);
	}

	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if ($this->recordsToLoad->isEmpty()) {
			return;
		}

		$piLifecycleStageProspectTable = piLifecycleStageProspectTable::getInstance();
		foreach ($this->recordsToLoad->getObjectDefinitions() as $definition) {

			$recordIds = $this->recordsToLoad->getRecordIdsByObjectDefinition($definition);
			$queryResults = $piLifecycleStageProspectTable->getLifecycleStageIdsByProspectIds($queryContext->getAccountId(), $recordIds);

			foreach ($queryResults as $queryResult) {
				$currLifecycleStageId = $queryResult['lifecycle_stage_id'];
				$currProspectId = $queryResult['prospect_id'];
				$this->loadedRecordIds->addRecordIdValue($definition, $currProspectId, $currLifecycleStageId);
				$this->recordsToLoad->removeRecordId($definition, $currProspectId);
			}

			foreach ($this->recordsToLoad->getRecordIdsByObjectDefinition($definition) as $currRecordId) {
				$this->loadedRecordIds->addRecordIdValue($definition, $currRecordId);
			}

		}

		$this->recordsToLoad->removeAllObjectsAndRecords();
	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
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

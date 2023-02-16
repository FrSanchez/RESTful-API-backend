<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;

class ProspectAccountBulkDataProcessor implements BulkDataProcessor
{
	/** @var array $recordsToLoadForProspectAccount */
	private array $recordsToLoadForProspectAccount;

	/** @var ObjectDefinition $referencedObjectDefinition */
	private ObjectDefinition $referencedObjectDefinition;

	/**
	 * Prospect Account Id to Prospect Account Representation
	 * @var array $prospectAccountIdToProspectAccount
	 */
	private array $prospectAccountIdToProspectAccount;

	/**
	 * FolderBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoadForProspectAccount = [];
		$this->prospectAccountIdToProspectAccount = [];
	}

	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		if ($objectDefinition->getType() === "Prospect" &&
			$selection->getRelationship()->getName() === "prospectAccount") {
			$queryBuilderNode->addSelection(SystemColumnNames::PROSPECT_ACCOUNT_ID);
		}
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (is_null($doctrineRecord)) {
			return;
		}

		$prospectAccountId = $doctrineRecord->get(SystemColumnNames::PROSPECT_ACCOUNT_ID);
		if (is_null($prospectAccountId)) {
			return;
		}

		if ($selection instanceof RelationshipSelection &&
			$selection->getRelationship()->getName() === "prospectAccount" &&
			!array_key_exists("prospectAccount", $dbArray) &&
			!$this->containsLoadedRecordForProspectAccount($prospectAccountId)
		) {
			$this->recordsToLoadForProspectAccount[$prospectAccountId] = true;
			$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
		}
	}

	/**
	 * @param int $prospectAccountId
	 * @return bool
	 */
	private function containsLoadedRecordForProspectAccount(int $prospectAccountId): bool
	{
		return array_key_exists($prospectAccountId, $this->prospectAccountIdToProspectAccount);
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (empty($this->recordsToLoadForProspectAccount)) {
			return;
		}

		$prospectAccountIds = array_keys($this->recordsToLoadForProspectAccount);

		// Get all the selection values we need for this object definition
		$recordSelections = array_values(BulkDataProcessorRelationshipHelper::getSelectionsForObjectDefinition(
			$selections,
			$objectDefinition,
			$this->referencedObjectDefinition
		));

		// Get the record id to record values map
		$recordIdToRecordCollection = BulkDataProcessorRelationshipHelper::getAssetDetails(
			$queryContext,
			$recordSelections,
			$prospectAccountIds,
			$this->referencedObjectDefinition
		);

		foreach ($prospectAccountIds as $prospectAccountId) {
			$prospectAccountRepresentation = null;
			if (array_key_exists($prospectAccountId, $recordIdToRecordCollection)) {
				$prospectAccountRepresentation = $recordIdToRecordCollection[$prospectAccountId];
			}

			$this->prospectAccountIdToProspectAccount[$prospectAccountId] = $prospectAccountRepresentation;
		}

		$this->recordsToLoadForProspectAccount = [];
	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}

		$prospectAccountId = $doctrineRecord->get(SystemColumnNames::PROSPECT_ACCOUNT_ID);
		if (is_null($prospectAccountId)) {
			$dbArray[$selection->getRelationship()->getName()] = null;
			return false;
		}

		if ($selection instanceof RelationshipSelection &&
			$selection->getRelationship()->getName() === "prospectAccount") {
			if (!array_key_exists($prospectAccountId, $this->prospectAccountIdToProspectAccount)) {
				return true;
			}

			$childRecord = $this->prospectAccountIdToProspectAccount[$prospectAccountId];
			$dbArray[$selection->getRelationship()->getName()] = $childRecord;
		}

		return false;
	}
}

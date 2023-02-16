<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use RuntimeException;

class UserBulkDataProcessor implements BulkDataProcessor
{
	private array $recordsToLoad;
	private ObjectDefinition $referencedObjectDefinition;
	private array $userIdToUser;

	/**
	 * UserBulkDataProcessor Constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoad = [];
		$this->userIdToUser = [];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$doctrineName = $selection->getRelationship()->getDoctrineName();
		// doctrine name in this object should have the field used to reference the user
		if ($objectDefinition->getFieldByName($doctrineName)) {
			$doctrineField = $objectDefinition->getFieldByName($doctrineName)->getDoctrineField();
			$queryBuilderNode->addSelection($doctrineField);
		} else {
			throw new RuntimeException("The bulkDataProcessor requires the related field in doctrineName");
		}
	}

	/**
	 * @inheritDoc
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if (is_null($doctrineRecord)) {
			return;
		}

		$doctrineName = $selection->getRelationship()->getDoctrineName();
		$doctrineField = $objectDefinition->getFieldByName($doctrineName)->getDoctrineField();
		if (!is_null($doctrineRecord->get($doctrineField))) {
			$this->recordsToLoad[$doctrineRecord->get($doctrineField)] = null;
		}
		$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		// Check if there are records that need loading
		if (count($this->recordsToLoad) == 0) {
			return;
		}

		$recordSelections = array_values(BulkDataProcessorRelationshipHelper::getSelectionsForObjectDefinition(
			$selections,
			$objectDefinition,
			$this->referencedObjectDefinition,
		));

		$recordIdToRecordCollection = BulkDataProcessorRelationshipHelper::getAssetDetails(
			$queryContext,
			$recordSelections,
			array_keys($this->recordsToLoad),
			$this->referencedObjectDefinition
		);

		foreach ($this->recordsToLoad as $piUserId => $empty) {
			$userRepresentation = null;
			if (array_key_exists($piUserId, $recordIdToRecordCollection)) {
				$userRepresentation = $recordIdToRecordCollection[$piUserId];
			}
			$this->userIdToUser[$piUserId] = $userRepresentation;
		}
		$this->recordsToLoad = [];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}

		$doctrineName = $selection->getRelationship()->getDoctrineName();
		$doctrineField = $objectDefinition->getFieldByName($doctrineName)->getDoctrineField();
		$currentRecordId = $doctrineRecord->get($doctrineField);
		if (is_null($currentRecordId)) {
			$dbArray[$selection->getRelationshipName()] = null;
			return false;
		}

		if (!array_key_exists($currentRecordId, $this->userIdToUser)) {
			return true;
		}

		$dbArray[$selection->getRelationshipName()] = $this->userIdToUser[$currentRecordId];
		return false;
	}
}

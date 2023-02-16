<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\SystemColumnNames;
use RuntimeException;
use TrackableAssetTrait;
use ReflectionException;
use piTrackerTable;
use generalTools;
use Doctrine_Query_Exception;

class TrackerBulkDataProcessorHelper
{
	/**
	 * Stores the information about the objects and the fields that are supported and also which fields need to be
	 * added to the primary query.
	 * @var array[][]
	 */
	const ALLOWED_OBJECT_FIELDS = [
		"File" => [
			"url" => [SystemColumnNames::ID],
			"vanityUrl" => [SystemColumnNames::ID],
			"isTracked" => [SystemColumnNames::ID],
		],
		"CustomRedirect" => [
			"vanityUrl" => [SystemColumnNames::ID],
			"trackedUrl" => [SystemColumnNames::ID],
		],
		"Form" => [
			"embedCode" => [SystemColumnNames::ID],
		],
		"LandingPage" => [
			"url" => [SystemColumnNames::ID],
			"vanityUrl" => [SystemColumnNames::ID],
		],
		"FormHandler" => [
			"embedCode" => [SystemColumnNames::ID],
			"url" => [SystemColumnNames::ID],
		]
	];

	/** @var RecordIdCollection $recordsToLoadForTracker */
	private RecordIdCollection $recordsToLoadForTracker;

	/** @var RecordIdValueCollection $loadedRecordsForTracker */
	private RecordIdValueCollection $loadedRecordsForTracker;

	/**
	 * Stores the tracker relationship name for each object definition
	 * @var array $objectNameToRelationshipNameForTracker
	 */
	private array $objectNameToRelationshipNameForTracker;

	/** @var int $version */
	private int $version;

	/**
	 * TrackerBulkDataProcessorHelper constructor.
	 * @param int $version
	 */
	public function __construct(int $version)
	{
		$this->recordsToLoadForTracker = new RecordIdCollection();
		$this->loadedRecordsForTracker = new RecordIdValueCollection();
		$this->objectNameToRelationshipNameForTracker = [];
		$this->version = $version;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @return bool
	 */
	private function doesObjectFieldExistInAllowedList(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection
	) : bool {
		if (!array_key_exists($objectDefinition->getType(), self::ALLOWED_OBJECT_FIELDS)) {
			return false;
		}

		$allowedFields = self::ALLOWED_OBJECT_FIELDS[$objectDefinition->getType()];
		$fieldName = $selection->getName();

		if (!array_key_exists($fieldName, $allowedFields)) {
			return false;
		}

		return true;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param QueryBuilderNode $queryBuilderNode
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		if (!$this->doesObjectFieldExistInAllowedList($objectDefinition, $selection)) {
			return;
		}

		$fieldName = $selection->getName();
		$fieldsToAdd = self::ALLOWED_OBJECT_FIELDS[$objectDefinition->getType()][$fieldName];
		foreach ($fieldsToAdd as $fieldToAdd) {
			$queryBuilderNode->addSelection($fieldToAdd);
		}
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @throws ReflectionException
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		?ImmutableDoctrineRecord $doctrineRecord
	): void {
		if (!$this->doesObjectFieldExistInAllowedList($objectDefinition, $selection)) {
			return;
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}

		// Get the Tracker Information
		$usesTrackableTrait = $doctrineRecord->isDoctrineUsingTrait(TrackableAssetTrait::class);
		if (!$usesTrackableTrait) {
			throw new RuntimeException("The doctrine record does not have the TrackableAssetTrait.");
		}

		$doctrineRecordClass = $doctrineRecord->getDoctrineRecordClass();
		$doctrineRecordObject = new $doctrineRecordClass();
		$relationshipName = $doctrineRecordObject->getTrackerRelationshipField();
		$this->objectNameToRelationshipNameForTracker[$objectDefinition->getType()] = $relationshipName;

		if (!$this->containsLoadedRecordForTracker($objectDefinition, $recordId)) {
			$this->recordsToLoadForTracker->addRecordId($objectDefinition, $recordId);
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext): void
	{
		if ($this->recordsToLoadForTracker->isEmpty()) {
			return;
		}

		$query = piTrackerTable::getInstance()->createQuery()
			->select('t.*')
			->from('piTracker t')
			->where('t.account_id = ?', $queryContext->getAccountId())
			->andWhere('t.prospect_id is null');

		$sqls = [];
		foreach ($this->objectNameToRelationshipNameForTracker as $objectName => $relationshipName) {
			$objectDefinition = $this->recordsToLoadForTracker->getObjectDefinitionByName($objectName);
			$recordIds = $this->recordsToLoadForTracker->getRecordIdsByObjectDefinition($objectDefinition);
			if (is_null($recordIds) || empty($recordIds)) {
				continue;
			}

			// assume that recordIds is a list of integers
			// build a SQL statement
			$sql = "(t." . $relationshipName . " IN (";
			for ($i = 0; $i < count($recordIds); $i++) {
				$sql .= generalTools::escapeSQL($recordIds[$i]);

				if ($i < count($recordIds) - 1) {
					$sql .= ', ';
				}
			}
			$sql .= '))';
			$sqls[] = $sql;
		}
		$query->andWhere('(' . join(' OR ', $sqls) . ')');
		$query->orderBy('t.id ASC');

		$results = $query->executeAndFree();

		foreach ($this->objectNameToRelationshipNameForTracker as $objectName => $relationshipName) {
			$objectDefinition = $this->recordsToLoadForTracker->getObjectDefinitionByName($objectName);

			foreach ($results as $resultRow) {
				$recordId = $resultRow[$relationshipName];
				if (isset($recordId) && !$this->loadedRecordsForTracker->containsRecordId($objectDefinition, $recordId)) {
					$this->loadedRecordsForTracker->addRecordIdValue($objectDefinition, $recordId, $resultRow);
					$this->recordsToLoadForTracker->removeRecordId($objectDefinition, $recordId);
				}
			}

			foreach ($this->recordsToLoadForTracker->getRecordIdsByObjectDefinition($objectDefinition) as $recordId) {
				$this->loadedRecordsForTracker->addRecordIdValue($objectDefinition, $recordId, null);
			}
		}

		$this->recordsToLoadForTracker->removeAllObjectsAndRecords();
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @return bool
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
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

		$fieldName = $selection->getName();
		if ($objectDefinition->getType() === "File" && $fieldName === "isTracked") {
			return $this->handleFileIsTrackedFieldModification(
				$objectDefinition,
				$currentRecordId,
				$fieldName,
				$dbArray
			);
		}

		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @return bool
	 */
	public function shouldModifyRecord(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection
	): bool {
		$fieldName = $selection->getName();
		return ($objectDefinition->getType() === "File" && $fieldName === "isTracked");
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $currentRecordId
	 * @param string $fieldName
	 * @param array $dbArray
	 * @return bool
	 */
	private function handleFileIsTrackedFieldModification(
		ObjectDefinition $objectDefinition,
		int $currentRecordId,
		string $fieldName,
		array &$dbArray
	): bool {
		if (!$this->containsLoadedRecordForTracker($objectDefinition, $currentRecordId)) {
			return true;
		}

		$dbArray[$fieldName] = $this->getLoadedValueForTracker($objectDefinition, $currentRecordId) ? true : false;
		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 */
	public function containsLoadedRecordForTracker(ObjectDefinition $objectDefinition, int $recordId): bool
	{
		return $this->loadedRecordsForTracker->containsRecordId($objectDefinition, $recordId);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return mixed|null
	 */
	public function getLoadedValueForTracker(ObjectDefinition $objectDefinition, int $recordId)
	{
		return $this->loadedRecordsForTracker->getRecordIdValueByObjectDefinition($objectDefinition, $recordId);
	}
}

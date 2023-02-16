<?php
namespace Api\Objects\Query;

use Api\DataTypes\ValueConverter;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use RuntimeException;

/**
 * Bulk data processor for "indirect relationships". This is a category of relationships where there is a mapping
 * table in between two tables, in which the mapping table has two foreign keys. An indirect relationship implies that
 * there is only a single valid value for one end of the relationship. For example, File to Folder is an indirect
 * relationship, in that it has a FolderObject between the two.
 *
 * Class IndirectRelationshipBulkDataProcessor
 * @package Api\Objects\Query
 */
abstract class IndirectRelationshipBulkDataProcessor implements BulkDataProcessor
{
	/** @var ObjectDefinition $referencedObjectDefinition */
	private $referencedObjectDefinition;

	/** @var RecordIdCollection $recordsToLoad */
	private $recordsToLoad;

	/**
	 * Cache for this already loaded/processed values
	 * @var array[] $loadedRecords
	 */
	private $loadedRecords = [];

	/**
	 * IndirectRelationshipBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoad = new RecordIdCollection();
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param QueryBuilderNode $queryBuilderNode
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $recordAsArray
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $recordAsArray
	): void {
		if (!($selection instanceof RelationshipSelection)) {
			throw new RuntimeException(
				'Unexpected selection specified. Expected it to be an instance of ' . RelationshipSelection::class
			);
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
		if (!array_key_exists($selection->getRelationship()->getName(), $recordAsArray)) {
			$recordId = $doctrineRecord->get(SystemColumnNames::ID);
			if (is_null($recordId)) {
				return;
			}

			// If we've already loaded this record previously, then we don't need to load it again
			if (!$this->containsLoadedRecord($objectDefinition, $recordId)) {
				$this->recordsToLoad->addRecordId($objectDefinition, $recordId);
			}
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Exception
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if ($this->recordsToLoad->isEmpty()) {
			return;
		}

		$recordIdToSecondaryRecordIdMap = $this->getValue($queryContext, $this->recordsToLoad);

		// Separate all of the IDs of the secondary records to load by object definition
		$secondaryRecordIdsToRetrieve = $this->getAllUniqueValues($recordIdToSecondaryRecordIdMap);

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
			$secondaryRecordIdsToRetrieve,
			$this->referencedObjectDefinition,
			$allowReadReplica
		);

		// Fan out each of the results back to the look up map
		foreach ($this->recordsToLoad->getObjectDefinitions() as $recordsToLoadObjectDefinition) {
			$recordIds = $this->recordsToLoad->getRecordIdsByObjectDefinition($recordsToLoadObjectDefinition);

			foreach ($recordIds as $recordId) {
				$secondaryRecordId = $recordIdToSecondaryRecordIdMap->getRecordIdValueByObjectDefinition(
					$recordsToLoadObjectDefinition,
					$recordId
				);

				$value = null;
				if (!is_null($secondaryRecordId)) {
					$value = $recordIdToRecordCollection[$secondaryRecordId] ?? null;
				}

				$this->appendLoadedRecords(
					$recordsToLoadObjectDefinition,
					$recordId,
					$value
				);
			}
		}

		// Reset the records to load
		$this->recordsToLoad = new RecordIdCollection();
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $recordAsArray
	 * @param ValueConverter $valueConverter
	 * @param int $apiVersion
	 * @return bool
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$recordAsArray,
		int $apiVersion
	): bool {
		if (is_null($doctrineRecord)) {
			return false;
		}

		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($currentRecordId)) {
			return false;
		}

		if (!$this->containsLoadedRecord($objectDefinition, $currentRecordId)) {
			return true;
		}

		$childRecord = $this->getLoadedRecord($objectDefinition, $currentRecordId);
		$recordAsArray[$selection->getRelationship()->getName()] = $childRecord;
		return false;
	}


	/**
	 * This function will be called with the query context and record id collections. The record id collection will
	 * contain all object definition and ids of those objects.
	 *
	 * The purpose of this method is to return record id value collection with the same object definition and
	 * record ids, but with also the value (record id) that refers to the object id for the object definition
	 * this provider is intended to be used for.
	 * For example, let's assume there is an indirect relationship between Assets and Folders. Whenever, the object
	 * API needs to get the folder information for each asset, this function will be called with all the object
	 * definitions and there record ids. Therefore, the return would be the definitions and record ids with value being
	 * the folder ids that the record ids map to.
	 *
	 * @param QueryContext $queryContext
	 * @param RecordIdCollection $recordIdCollection
	 * @return RecordIdValueCollection
	 */
	abstract protected function getValue(QueryContext $queryContext, RecordIdCollection $recordIdCollection): RecordIdValueCollection;

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return array|null
	 */
	private function getLoadedRecord(ObjectDefinition $objectDefinition, int $recordId): ?array
	{
		if (!isset($this->loadedRecords[$objectDefinition->getType()]) ||
			!isset($this->loadedRecords[$objectDefinition->getType()][$recordId])) {
			return null;
		}

		return $this->loadedRecords[$objectDefinition->getType()][$recordId];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @param array|null $recordAsArray
	 */
	private function appendLoadedRecords(ObjectDefinition $objectDefinition, int $recordId, ?array $recordAsArray): void
	{
		if (!isset($this->loadedRecords[$objectDefinition->getType()])) {
			$this->loadedRecords[$objectDefinition->getType()] = [];
		}

		// Assume that new values are newer than previous so overwrite any existing values
		$this->loadedRecords[$objectDefinition->getType()][$recordId] = $recordAsArray;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 */
	private function containsLoadedRecord(ObjectDefinition $objectDefinition, int $recordId): bool
	{
		return isset($this->loadedRecords[$objectDefinition->getType()]) &&
			array_key_exists($recordId, $this->loadedRecords[$objectDefinition->getType()]);
	}

	/**
	 * Given a collection of values by record ID, return all unique values.
	 * @param RecordIdValueCollection $recordIdValueCollection
	 * @return array
	 */
	private function getAllUniqueValues(RecordIdValueCollection $recordIdValueCollection): array
	{
		$secondaryRecordIdsToRetrieve = [];
		foreach ($recordIdValueCollection->getObjectDefinitions() as $recordsToLoadObjectDefinition) {
			$ids = $this->recordsToLoad->getRecordIdsByObjectDefinition($recordsToLoadObjectDefinition);

			foreach ($ids as $id) {
				$secondaryRecordId = $recordIdValueCollection->getRecordIdValueByObjectDefinition(
					$recordsToLoadObjectDefinition,
					$id
				);
				if (!is_null($secondaryRecordId)) {
					$secondaryRecordIdsToRetrieve[$secondaryRecordId] = true;
				}
			}
		}
		return array_keys($secondaryRecordIdsToRetrieve);
	}
}

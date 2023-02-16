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
use RuntimeException;

/**
 * Bulk data processor for "bulk fields". This is a category of fields where we need to execute a second query
 * in order to get data for the current field. For example, getting the tracker information for a file object.
 *
 * This Field Bulk Data Processor assumes that this will be used to retrieve only 1 kind of field (like folder id for
 * all objects). If the processor can be used for multiple fields, it will be need to be defined separately for those
 * specific set of fields.
 *
 * Class SingleFieldBulkDataProcessor
 * @package Api\Objects\Query
 */
abstract class SingleFieldBulkDataProcessor implements BulkDataProcessor
{
	/** @var RecordIdCollection $recordsToLoad */
	private $recordsToLoad;

	/**
	 * Cache for this already loaded/processed values
	 * @var array[] $loadedRecords
	 */
	private $loadedRecords = [];

	/**
	 * SingleFieldBulkDataProcessor constructor.
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
		if (!($selection instanceof FieldDefinition)) {
			throw new RuntimeException(
				'Unexpected selection specified. Expected it to be an instance of ' . FieldDefinition::class
			);
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}

		// If we've already loaded this record previously, then we don't need to load it again
		if (!$this->containsLoadedRecord($objectDefinition, $recordId)) {
			$this->recordsToLoad->addRecordId($objectDefinition, $recordId);
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if ($this->recordsToLoad->isEmpty()) {
			return;
		}

		$recordIdToSecondaryRecordIdMap = $this->getValue($queryContext, $this->recordsToLoad);
		foreach ($this->recordsToLoad->getObjectDefinitions() as $recordsToLoadObjectDefinition) {
			$recordIds = $this->recordsToLoad->getRecordIdsByObjectDefinition($recordsToLoadObjectDefinition);

			foreach ($recordIds as $recordId) {
				$secondaryRecord = $recordIdToSecondaryRecordIdMap->getRecordIdValueByObjectDefinition(
					$recordsToLoadObjectDefinition,
					$recordId
				);

				$this->appendLoadedRecords($recordsToLoadObjectDefinition, $recordId, $secondaryRecord);
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

		$fieldName = $selection->getName();
		$recordAsArray[$fieldName] = $this->getLoadedValue($objectDefinition, $currentRecordId);
		return false;
	}

	/**
	 * This function will be called with the query context and record id collections. The record id collection will
	 * contain all object definition and ids of those objects.
	 *
	 * The purpose of this method is to return record id value collection with the same object definition and
	 * record ids, but with also the value that refers to the object id for the object definition this provider is
	 * intended to be used for.
	 *
	 * For example, let's assume there is an indirect field between Assets and Folder Ids. Whenever, the object
	 * API needs to get the folder id information for each asset, this function will be called with all the object
	 * definitions and there record ids. Therefore, the return would be the definitions and record ids with value being
	 * the folder ids that the record ids map to.
	 *
	 * @param QueryContext $queryContext
	 * @param RecordIdCollection $recordIdCollection
	 * @return RecordIdValueCollection
	 */
	abstract protected function getValue(
		QueryContext $queryContext,
		RecordIdCollection $recordIdCollection
	): RecordIdValueCollection;

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
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @param mixed|null $recordAsArray
	 */
	private function appendLoadedRecords(ObjectDefinition $objectDefinition, int $recordId, $recordAsArray): void
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
	 * @return mixed|null
	 */
	private function getLoadedValue(ObjectDefinition $objectDefinition, int $recordId)
	{
		if (!isset($this->loadedRecords[$objectDefinition->getType()]) ||
			!array_key_exists($recordId, $this->loadedRecords[$objectDefinition->getType()])) {
			return null;
		}

		return $this->loadedRecords[$objectDefinition->getType()][$recordId];
	}
}

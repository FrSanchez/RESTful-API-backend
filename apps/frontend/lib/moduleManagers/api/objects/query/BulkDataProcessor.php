<?php
namespace Api\Objects\Query;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Relationships\RelationshipSelection;
use Doctrine_Exception;

/**
 * An instance of a BulkDataProcessor. Implementations are in charge of checking loaded records to see if they require
 * data to be fetched, loading that data and then modifying the record with the loaded data. A new instance of this
 * processor is instantiated for each query.
 *
 * The implementation methods will be called in the following order:
 * 1. {@see BulkDataProcessor::checkAndAddRecordToLoadIfNeedsLoading}
 * 2. {@see BulkDataProcessor::fetchData}
 * 3. {@see BulkDataProcessor::modifyRecord}
 *
 * The steps above are repeated multiple times until all data processors have returned that no further data is required
 * to be loaded.
 *
 * Interface BulkDataProcessor
 * @package Api\Objects\Query
 */
interface BulkDataProcessor
{
	const DEFAULT_VERSION = 5;

	/**
	 * Allows for additional fields to be added to the primary query. These additional fields will be loaded into the
	 * records and passed to the other methods in the processor. This allows for extra field data to be used in the
	 * calculation of the value for a field or relationship.
	 *
	 * @param ObjectDefinition $objectDefinition The object definition of the record to be loaded.
	 * @param FieldDefinition|FieldSelection|RelationshipSelection|CollectionSelection $selection The selection currently being processed.
	 * @param QueryBuilderNode $queryBuilderNode The current query to be executed.
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void;

	/**
	 * Checks the record if data needs to be loaded. This method is invoked for every record within the result set
	 * before the record has been converted to a representation. The BulkDataProcessor should _not_ load the data within
	 * this method since it will not be performant with large query results. The BulkDataProcessor should keep any
	 * references to the data in order to fetch the data in a later step (see {@see fetchData}).
	 *
	 * The $dbArray argument is the in-progress record being built. It contains data from the primary query and any
	 * previous cycles while building the record data. The key of the DB array is API name and the value is the DB
	 * formatted value.
	 *
	 * For example, given a selection like "createdAt", which is a field with Doctrine name "created_at", the DB array
	 * will contain a key of "createdAt" with value of a string containing the MySQL formatted date.
	 *
	 * <code>
	 * [
	 *   [ "createdAt => "2020-04-20 00:00:00" ]
	 * ]
	 * </code>
	 *
	 * @param ObjectDefinition $objectDefinition The object definition of the loaded record.
	 * @param FieldDefinition|FieldSelection|RelationshipSelection|CollectionSelection $selection The selection currently being processed.
	 * @param ImmutableDoctrineRecord|null $doctrineRecord The doctrine record from database
	 * @param array $dbArray The record loaded from the primary query.
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void;

	/**
	 * Fetches the data that is required to modify the record. This method is invoked once per query after each of the
	 * records have been been checked (using the {@see checkAndAddRecordToLoadIfNeedsLoading} function). The
	 * BulkDataProcessor should keep all of the state information so that it can be used during the modify step
	 * (@param QueryContext $queryContext The context of the executed query.
	 * @param ObjectDefinition $objectDefinition The object definition of the executed query.
	 * @param array $selections The selections made on the executed query.
	 * @param bool $allowReadReplica The caller can suggest to go to read replica during fetch
	 * @throws Doctrine_Exception
	 *@see modifyRecord}.
	 *
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void;

	/**
	 * Modifies the record with the new data fetched during this cycle.
	 *
	 * The DB array should be modified so that the key of the array is the API name and the value is the DB formatted
	 * value. A step after this method is called will convert the value from the DB value into the API value or the
	 * server value, depending on where the record is needed.
	 *
	 * For example, given a selection like "createdAt", which is a field with Doctrine name "created_at", the DB array
	 * should contain a key of "createdAt" with value of a string containing the MySQL formatted date.
	 *
	 * <code>
	 * [
	 *   [ "createdAt => "2020-04-20 00:00:00" ]
	 * ]
	 * </code>
	 *
	 * @param ObjectDefinition $objectDefinition The object definition of the loaded record.
	 * @param FieldDefinition|FieldSelection|RelationshipSelection|CollectionSelection $selection The selection currently being processed.
	 * @param ImmutableDoctrineRecord|null $doctrineRecord The doctrine record from database
	 * @param array $dbArray The record loaded from the primary query.
	 * @param int $apiVersion The version of the API the request is using
	 * @return bool True if the BulkDataProcessor needs to be invoked again.
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion
	): bool;
}

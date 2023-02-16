<?php
namespace Api\Objects\Query;

use Api\Exceptions\ApiException;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Representations\RepresentationBuilderContext;
use Api\Metrics\RequestTiming;
use Api\Objects\Access\AccessContext;
use Api\Objects\Access\MultipleRecordAccessResponse;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectRecordRepresentationFactory;
use Api\Objects\RecordIdCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\Access\ObjectAccessManager;
use Api\Objects\SystemColumnNames;
use Api\Objects\SystemFieldNames;
use Doctrine_Exception;
use Doctrine_Query;
use ApiErrorLibrary;
use Doctrine_Query_Exception;
use RESTClient;

/**
 * Manager for querying for object records.
 *
 * Class ObjectQueryManager
 * @package Api\Objects\Query
 */
class ObjectQueryManager
{
	/** @var ObjectRecordRepresentationFactory */
	private $objectRecordRepresentationFactory;

	/** @var ObjectAccessManager $objectAccessManager */
	private $objectAccessManager;

	/** @var BulkDataManager $bulkDataManager */
	private $bulkDataManager;

	public function __construct(
		ObjectRecordRepresentationFactory $objectRecordRepresentationFactory,
		ObjectAccessManager $objectAccessManager,
		BulkDataManager $bulkDataManager
	) {
		$this->objectRecordRepresentationFactory = $objectRecordRepresentationFactory;
		$this->objectAccessManager = $objectAccessManager;
		$this->bulkDataManager = $bulkDataManager;
	}

	/**
	 * @param QueryContext $queryContext
	 * @param SingleResultQuery $query
	 * @return SingleResultQueryResult
	 * @throws Doctrine_Exception
	 */
	public function queryOne(QueryContext $queryContext, SingleResultQuery $query): SingleResultQueryResult
	{
		$queryResult = $this->doQuery($queryContext, $query, false);
		if ($queryResult->isEmpty()) {
			return new SingleResultQueryResult(null, [], new RecordIdCollection());
		}
		return new SingleResultQueryResult(
			$queryResult->getRepresentation(0),
			$queryResult->getAdditionalFields(0),
			$queryResult->getRedactedRecordIds()
		);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ManyQuery $query
	 * @return ManyQueryResult
	 * @throws Doctrine_Exception
	 */
	public function queryMany(QueryContext $queryContext, ManyQuery $query): ManyQueryResult
	{
		return $this->doQuery($queryContext, $query, true);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param AbstractQuery $query
	 * @param bool $allowReadReplicaForBulkLoaders
	 * @return array
	 * @throws Doctrine_Exception
	 * @throws Doctrine_Query_Exception
	 */
	public function getDatabaseRepresentationForQuery(QueryContext $queryContext, AbstractQuery $query, bool $allowReadReplicaForBulkLoaders): array
	{
		RequestTiming::getInstance()->addTiming(RequestTiming::DB_QUERY);
		$objectDefinition = $query->getObjectDefinition();
		$selections = $query->getSelection();

		// Add any additional fields
		if ($objectDefinition->getCustomFieldProvider()) {
			$query->addSelectAdditionalFields(SystemFieldNames::ID);
		}
		$fieldsToRemove = [];
		foreach ($query->getSelectAdditionalFields() as $selectAdditionalField) {
			$fieldAdded = $this->appendFieldDefinition($selections, $objectDefinition, $selectAdditionalField);
			if ($fieldAdded) {
				$fieldsToRemove[$selectAdditionalField] = true;
			}
		}

		// For each of the relationship selections made by the user, we need to fetch additional data so that the
		// logic in queryOne functions properly (like the ID field for checking record access).
		$this->addAdditionalFieldsForRelationships($selections, $fieldsToRemove);

		// Query the record to be returned using the ObjectDefinition and the fields provided by the user.
		$queryModifier = $objectDefinition->getDoctrineQueryModifier();
		$doctrineQuery = $queryModifier->createDoctrineQuery($queryContext, $selections);

		// Apply any where conditions
		foreach ($query->getWhereConditions() as $whereCondition) {
			$whereCondition->applyToDoctrineQuery($doctrineQuery);
		}

		// If this object type has an is_archived column but does not expose it as isDeleted, we need to filter out
		//   archived items regardless of where conditions passed in.  (If there is an isDeleted field, we let the
		//   caller decide.)
		if ($objectDefinition->isArchivable() &&
			!$objectDefinition->getStandardFieldByName(SystemFieldNames::IS_DELETED)) {
			$doctrineQuery->addWhere('is_archived = ?', false);
		}

		$this->applyOrderByToDoctrineQuery($doctrineQuery, $query);
		$doctrineQuery->limit($query->getLimit());
		$doctrineQuery->offset($query->getOffset());

		RequestTiming::getInstance()->addTiming(RequestTiming::QUERY_PRIMARY);
		$doctrineCollection = $doctrineQuery->executeAndFree();
		if (is_null($doctrineCollection) || $doctrineCollection->count() == 0) {
			return [$selections, $fieldsToRemove, []];
		}
		RequestTiming::getInstance()->stopTiming(RequestTiming::QUERY_PRIMARY);

		// Transform the records from Doctrine classes to associative arrays
		$dbArrays = $queryModifier->convertDoctrineCollectionToDatabaseArrays(
			$queryContext->getVersion(),
			$doctrineCollection,
			$selections
		);

		RequestTiming::getInstance()->addTiming(RequestTiming::QUERY_BULK_PROCESSING);
		$this->bulkDataManager->processRecordsForBulkData(
			$queryContext,
			$doctrineCollection,
			$dbArrays,
			$selections,
			$objectDefinition,
			$allowReadReplicaForBulkLoaders
		);
		unset($doctrineCollection);
		RequestTiming::getInstance()->stopTiming(RequestTiming::QUERY_BULK_PROCESSING);

		return [$selections, $fieldsToRemove, $dbArrays];
	}

	/**
	 * @param QueryContext $queryContext
	 * @param AbstractQuery $query
	 * @param bool $allowReadReplicaForBulkLoaders
	 * @return ManyQueryResult
	 * @throws Doctrine_Exception
	 * @throws Doctrine_Query_Exception
	 */
	private function doQuery(QueryContext $queryContext, AbstractQuery $query, bool $allowReadReplicaForBulkLoaders): ManyQueryResult
	{
		list($selections, $fieldsToRemove, $dbArrays) = $this->getDatabaseRepresentationForQuery($queryContext, $query, $allowReadReplicaForBulkLoaders);
		if (empty($dbArrays)) {
			return ManyQueryResult::getEmptyManyQueryResult();
		}

		$objectDefinition = $query->getObjectDefinition();
		$queryModifier = $objectDefinition->getDoctrineQueryModifier();

		RequestTiming::getInstance()->addTiming(RequestTiming::QUERY_CONVERT_ARRAY);
		$representationArrays = $queryModifier->convertDatabaseArraysToServerArrays(
			$queryContext->getVersion(),
			$dbArrays,
			$selections
		);
		$lastNonRedactedRecord = null;
		if ($query instanceof ManyQuery && count($representationArrays) >= $query->getLimit()) {
			$lastNonRedactedRecord = $this->extractLastRecordFields($query, $representationArrays[array_key_last($representationArrays)]);
		}
		RequestTiming::getInstance()->stopTiming(RequestTiming::QUERY_CONVERT_ARRAY);

		RequestTiming::getInstance()->addTiming(RequestTiming::QUERY_REMOVE_INACCESSIBLE);
		$redactedRecordIds = new RecordIdCollection();
		$this->removeInaccessibleObjectsFromRecords(
			$queryContext->getAccessContext(),
			$selections,
			$representationArrays,
			$redactedRecordIds,
			$objectDefinition
		);
		RequestTiming::getInstance()->stopTiming(RequestTiming::QUERY_REMOVE_INACCESSIBLE);

		$representations = [];
		$allAdditionalFieldValues = [];
		foreach ($representationArrays as $representationAsArray) {
			if (is_null($representationAsArray)) {
				continue;
			}

			// retrieve the "additionalFields" selected by the the user
			$additionalFieldValues = $this->buildAdditionalFieldsResult(
				$representationAsArray,
				$query->getSelectAdditionalFields()
			);

			// If the user didn't request the field and we added it, then we must remove it
			// before creating the representation so that it doesn't get returned to the user.
			self::removeKeysRecursive($representationAsArray, $fieldsToRemove);

			// Transform the representation from associative array to the Representation
			$representation = $this->objectRecordRepresentationFactory
				->createRecordRepresentationForObjectFromArray($objectDefinition, $representationAsArray, new RepresentationBuilderContext($queryContext->getAccountId(),$queryContext->getVersion()));

			$representations[] = $representation;
			$allAdditionalFieldValues[] = $additionalFieldValues;
		}
		RequestTiming::getInstance()->stopTiming(RequestTiming::DB_QUERY);

		return new ManyQueryResult($representations, $allAdditionalFieldValues, $redactedRecordIds, $lastNonRedactedRecord);
	}

	/**
	 * @param array $data
	 * @param string[] $selections
	 * @return array
	 */
	private function buildAdditionalFieldsResult(array $data, array $selections): array
	{
		$result = [];
		foreach ($selections as $selection) {
			$result[$selection] = isset($data[$selection]) ? $data[$selection] : null;
		}
		return $result;
	}

	/**
	 * Removes keys from the specified array. This method takes in an associative array where the key is the key to be
	 * removed and the value is either a true or another associative array. If the value is a true, then the value is
	 * removed. If the value is an associative array, only the specified keys will be removed (recursively).
	 *
	 * Example
	 * <code>
	 * $array = [
	 *   'user' => ['id' => 100, 'username' => 'tony'],
	 *   'createdAt' => 'yesterday'
	 * ];
	 *
	 * $keysToRemove = [
	 *   'user' => ['id' => true],
	 *   'createdAt' => true
	 * ];
	 *
	 * $this->removeKeysRecursive($array, $keysToRemove);
	 *
	 * // ['user' => ['id' => 100], 'createdAt' => 'yesterday']
	 * // $array = ['user' => ['username' => 'tony']]
	 * </code>
	 *
	 * @param array $array The array to be modified (in-place).
	 * @param array $keysToRemove
	 */
	public static function removeKeysRecursive(array &$array, array $keysToRemove): void
	{
		foreach ($keysToRemove as $key => $valueToRemove) {
			if (!array_key_exists($key, $array)) {
				// the original array doesn't contain the value so we don't need to remove it
				continue;
			} elseif ($valueToRemove === true) {
				// the value is a boolean, so just remove it in it's entirety
				unset($array[$key]);
			} elseif (is_array($valueToRemove) && !is_null($array[$key])) {
				// if the original array contain an object (not null), then iterate over the array of keys
				// to remove within the sub-object
				self::removeKeysRecursive($array[$key], $valueToRemove);
			}
		}
	}

	/**
	 * @param FieldDefinition[]|FieldSelection[] $selectedFieldDefinitions
	 * @param ObjectDefinition $objectDefinition
	 * @param string $fieldName
	 * @return bool True when the field was updated_at field added.
	 */
	private function appendFieldDefinition(array &$selectedFieldDefinitions, ObjectDefinition $objectDefinition, string $fieldName): bool
	{
		$fieldDefinition = $objectDefinition->getFieldByName($fieldName);
		if (!$fieldDefinition) {
			// If the object doesn't have the specified field, then we don't need to add it.
			return false;
		}

		foreach ($selectedFieldDefinitions as $selectedFieldDefinition) {
			if ($selectedFieldDefinition instanceof FieldSelection &&
				$selectedFieldDefinition->getFieldDefinition()->getName() == $fieldDefinition->getName()) {
				// if the field was already in the collection, then we didn't need to add it
				return false;
			}
		}

		// We have looked through all of the selected fields and the wasn't there so add it.
		$selectedFieldDefinitions[] = new FieldScalarSelection($fieldDefinition);
		return true;
	}

	/**
	 * For each relationship selections, do the recursion to add all necessary fields for validation
	 * @param array $selections
	 * @param array $fieldsToRemove
	 */
	private function addAdditionalFieldsForRelationships(array $selections, array &$fieldsToRemove): void
	{
		foreach ($selections as $selection) {
			if (!($selection instanceof RelationshipSelection)) {
				continue;
			}
			$childRelationshipName = $selection->getRelationship()->getName();
			$fieldsToRemove[$childRelationshipName] = [];
			$this->addAdditionalFieldsForRelationship($selection, $fieldsToRemove[$childRelationshipName]);
			if (count($fieldsToRemove[$childRelationshipName]) == 0) {
				unset($fieldsToRemove[$childRelationshipName]);
			}
		}
	}

	/**
	 * For each relationship, add all the additional fields. For now, we only need the id field.
	 * @param RelationshipSelection $selection
	 * @param array $fieldsToRemove
	 */
	private function addAdditionalFieldsForRelationship(RelationshipSelection $selection, array &$fieldsToRemove): void
	{
		$idField = $selection->getReferencedObjectDefinition()->getStandardFieldByName(SystemFieldNames::ID);

		// if the user didn't select the ID field, then add the field and mark that it can be removed later
		if ($idField && !$selection->containsField($idField)) {
			// add the ID field to the selection
			$selection->appendFieldSelection(new FieldScalarSelection($idField));

			// make sure to add it to the fields to remove
			$fieldsToRemove[$idField->getName()] = true;
		}

		foreach ($selection->getChildRelationshipsSelection() as $childSelection) {
			$childRelationshipName = $childSelection->getRelationship()->getName();
			$fieldsToRemove[$childRelationshipName] = [];
			$this->addAdditionalFieldsForRelationship($childSelection, $fieldsToRemove[$childRelationshipName]);
			if (count($fieldsToRemove[$childRelationshipName]) == 0) {
				unset($fieldsToRemove[$childRelationshipName]);
			}
		}
	}

	/**
	 * For each relationship selections, do the recursion to validate the access
	 * @param AccessContext $accessContext
	 * @param array $selections
	 * @param array $representationArrays
	 * @param RecordIdCollection $redactedRecordIds Adds any IDs that were redacted to this instance.
	 * @param ObjectDefinition $objectDefinition
	 */
	private function removeInaccessibleObjectsFromRecords(
		AccessContext $accessContext,
		array $selections,
		array &$representationArrays,
		RecordIdCollection $redactedRecordIds,
		ObjectDefinition $objectDefinition
	): void {
		// Walk the record like a graph, collecting the object definitions and record IDs.
		$recordIdCollection = new RecordIdCollection();

		foreach ($representationArrays as $representationArray) {
			$this->collectRecordIds($recordIdCollection, $selections, $representationArray, $objectDefinition);
		}

		// Check the accessibility of each record ID against the object access manager.
		$accessibleRecordIds = $this->objectAccessManager->canUserAccessRecords($accessContext, $recordIdCollection);

		foreach ($representationArrays as $key => $representationArray) {
			$this->filterRecord(
				$accessibleRecordIds,
				$selections,
				$representationArray,
				$redactedRecordIds,
				$objectDefinition
			);

			if (is_null($representationArray)) {
				unset($representationArrays[$key]);
			}
		}
	}

	/**
	 * @param RecordIdCollection $recordIdCollection
	 * @param array $selections
	 * @param array $record
	 * @param ObjectDefinition $objectDefinition
	 */
	private function collectRecordIds(
		RecordIdCollection $recordIdCollection,
		array $selections,
		array $record,
		ObjectDefinition $objectDefinition
	): void {
		// Add the primary object for access check as well in the case of query.
		if (isset($record[SystemFieldNames::ID])) {
			$recordIdCollection->addRecordId($objectDefinition, $record[SystemFieldNames::ID]);
		}

		foreach ($selections as $selection) {
			if (!($selection instanceof RelationshipSelection)) {
				continue;
			}
			$this->collectRelatedRecordIds($recordIdCollection, $selection, $record);
		}
	}

	/**
	 * @param RecordIdCollection $recordIdCollection
	 * @param RelationshipSelection $selection
	 * @param array $record
	 */
	private function collectRelatedRecordIds(
		RecordIdCollection $recordIdCollection,
		RelationshipSelection $selection,
		array $record
	): void {
		$relatedRecordObjectDefinition = $selection->getReferencedObjectDefinition();
		$relatedRecord = $record[$selection->getRelationship()->getName()];

		if (isset($relatedRecord[SystemFieldNames::ID])) {
			$recordIdCollection->addRecordId($relatedRecordObjectDefinition, $relatedRecord[SystemFieldNames::ID]);
		}

		foreach ($selection->getChildRelationshipSelections() as $childRelationshipSelection) {
			if (isset($relatedRecord[$childRelationshipSelection->getRelationship()->getName()])) {
				$this->collectRelatedRecordIds($recordIdCollection, $childRelationshipSelection, $relatedRecord);
			}
		}
	}

	/**
	 * @param MultipleRecordAccessResponse $accessibleRecordIds
	 * @param array $selections
	 * @param array $record
	 * @param RecordIdCollection $redactedRecordIds
	 * @param ObjectDefinition $objectDefinition
	 */
	private function filterRecord(
		MultipleRecordAccessResponse $accessibleRecordIds,
		array $selections,
		array &$record,
		RecordIdCollection $redactedRecordIds,
		ObjectDefinition $objectDefinition
	): void {
		if (isset($record[SystemFieldNames::ID])) {
			// if the user can't access this record, then set the value to null
			if (!$accessibleRecordIds->canUserAccessRecord($objectDefinition, $record[SystemFieldNames::ID])) {
				$redactedRecordIds->addRecordId($objectDefinition, $record[SystemFieldNames::ID]);
				$record = null;

				// no need to continue since all of the child records will be removed also
				return;
			}
		}

		// Walk the record again, redacting all data that the user doesn't have access to.
		foreach ($selections as $selection) {
			if (!($selection instanceof RelationshipSelection)) {
				continue;
			}
			$this->filterRelatedRecords($accessibleRecordIds, $selection, $record, $redactedRecordIds);
		}
	}

	/**
	 * @param MultipleRecordAccessResponse $accessibleRecords
	 * @param RelationshipSelection $selection
	 * @param array $record
	 * @param RecordIdCollection $redactedRecordIds
	 */
	private function filterRelatedRecords(
		MultipleRecordAccessResponse $accessibleRecords,
		RelationshipSelection $selection,
		array &$record,
		RecordIdCollection $redactedRecordIds
	): void {
		$relatedRecordObjectDefinition = $selection->getReferencedObjectDefinition();
		$relationshipName = $selection->getRelationship()->getName();
		$relatedRecord = &$record[$relationshipName];

		if (isset($relatedRecord[SystemFieldNames::ID])) {
			$relatedRecordId = $relatedRecord[SystemFieldNames::ID];

			// if the user can't access this related record, then set the value to null
			if (!$accessibleRecords->canUserAccessRecord($relatedRecordObjectDefinition, $relatedRecordId)) {
				$record[$relationshipName] = null;
				$redactedRecordIds->addRecordId($relatedRecordObjectDefinition, $relatedRecordId);

				// no need to continue since all of the child records will be removed also
				return;
			}
		}

		foreach ($selection->getChildRelationshipSelections() as $childRelationshipSelection) {
			$childRelationshipName = $childRelationshipSelection->getRelationship()->getName();
			if (isset($relatedRecord[$childRelationshipName])) {
				$this->filterRelatedRecords(
					$accessibleRecords,
					$childRelationshipSelection,
					$relatedRecord,
					$redactedRecordIds
				);
			}
		}
	}

	/**
	 * For the relationship selections, do the validate the access
	 * @param AccessContext $accessContext
	 * @param RelationshipSelection $relationshipSelection
	 * @param array $record
	 * @return bool
	 */
	private function hasAccessForRelationship(
		AccessContext $accessContext,
		RelationshipSelection $relationshipSelection,
		array &$record
	): bool {
		// Whenever there is no value for a relationship, empty array is given
		if (empty($record)) {
			return true;
		}

		if (isset($record[SystemFieldNames::ID])) {
			$recordId = $record[SystemFieldNames::ID];
			$hasAccessToRecord = $this->objectAccessManager->canUserAccessRecord(
				$accessContext,
				$relationshipSelection->getReferencedObjectDefinition(),
				$recordId
			);

			if (!$hasAccessToRecord) {
				return false;
			}
		}

		foreach ($relationshipSelection->getChildRelationshipsSelection() as $childSelection) {
			$hasPermission = $this->hasAccessForRelationship(
				$accessContext,
				$childSelection,
				$record[$childSelection->getRelationship()->getName()]
			);

			if (!$hasPermission) {
				$record[$childSelection->getRelationship()->getName()] = [];
			}
		}

		return true;
	}

	/**
	 * @param Doctrine_Query $doctrineQuery
	 * @param AbstractQuery $query
	 */
	private function applyOrderByToDoctrineQuery(Doctrine_Query $doctrineQuery, AbstractQuery $query): void
	{
		if (!($query instanceof ManyQuery)) {
			return;
		}

		$orderByStmt = [];
		$foundIdSorts = false;
		$direction = null;

		foreach ($query->getOrderBy() as $orderByPair) {
			$doctrineFieldName = $orderByPair->getFieldDefinition()->getDoctrineField();
			$orderByStmt[] = $doctrineFieldName . ' ' . $orderByPair->getDirection();

			if (strcmp($doctrineFieldName, SystemColumnNames::ID) === 0) {
				$foundIdSorts = true;
			}

			if (is_null($direction)) {
				$direction = $orderByPair->getDirection();
			}

			if (strcmp($direction, $orderByPair->getDirection()) !== 0) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
					"the direction of all the orderBy's must be the same.",
					RESTClient::HTTP_BAD_REQUEST
				);
			}
		}

		if (is_null($direction)) {
			$direction = OrderByPair::DIRECTION_ASC;
		}

		if (!$foundIdSorts) {
			$orderByStmt[] = SystemColumnNames::ID . ' ' . $direction;
		}

		$doctrineQuery->orderBy(join(',', $orderByStmt));
	}

	private function extractLastRecordFields(AbstractQuery $query, array $representationArray) : array
	{
		$lastRecord = [];
		foreach($query->getSelectAdditionalFields() as $field) {
			$lastRecord[$field] = $representationArray[$field];
		}
		return $lastRecord;
	}
}

<?php
namespace Api\Config\BulkDataProcessors;

use Api\Exceptions\ApiException;
use Api\Objects\Access\AccessException;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use FolderManager;
use ApiErrorLibrary;
use FolderConstants;
use Exception;
use piFolderObjectTable;
use piFolderObject;
use Doctrine_Exception;

class FolderBulkDataProcessor implements BulkDataProcessor
{
	/** @var RecordIdCollection $recordsToLoadForFolderId */
	private RecordIdCollection $recordsToLoadForFolderId;

	/**
	 * Cache for this already loaded/processed values
	 * @var RecordIdValueCollection $loadedFolderRecordIds
	 */
	private RecordIdValueCollection $loadedFolderRecordIds;

	/** @var ObjectDefinition $referencedObjectDefinition */
	private ObjectDefinition $referencedObjectDefinition;

	/** @var RecordIdCollection $recordsToLoadForFolder */
	private RecordIdCollection $recordsToLoadForFolder;

	/**
	 * Folder Id to folder Representation
	 * @var array $folderIdToFolder
	 */
	private array $folderIdToFolder;

	/**
	 * FolderBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoadForFolderId = new RecordIdCollection();
		$this->loadedFolderRecordIds = new RecordIdValueCollection();
		$this->recordsToLoadForFolder = new RecordIdCollection();
		$this->folderIdToFolder = [];
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
	 * @param array $dbArray
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}

		if ($selection instanceof RelationshipSelection &&
			$selection->getRelationship()->getName() === "folder" &&
			!array_key_exists("folder", $dbArray) &&
			!$this->containsLoadedRecordForFolder($objectDefinition, $recordId)
		) {
			$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
			$this->recordsToLoadForFolderId->addRecordId($objectDefinition, $recordId);
			$this->recordsToLoadForFolder->addRecordId($objectDefinition, $recordId);
		}

		if ($selection instanceof FieldDefinition &&
			($selection->getName() === "folderId" || $selection->getName() === "folder_id") &&
			!$this->containsLoadedRecordForFolderId($objectDefinition, $recordId)
		) {
			$this->recordsToLoadForFolderId->addRecordId($objectDefinition, $recordId);
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
		$this->loadFolderIds($queryContext);
		$this->loadFolders($queryContext, $objectDefinition, $selections);
	}

	/**
	 * @param QueryContext $queryContext
	 */
	private function loadFolderIds(QueryContext $queryContext): void
	{
		if ($this->recordsToLoadForFolderId->isEmpty()) {
			return;
		}

		$this->getRecordIdToFolderIdMap($queryContext);
		$this->recordsToLoadForFolderId = new RecordIdCollection();
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @throws Doctrine_Exception
	 */
	private function loadFolders(
		QueryContext $queryContext,
		ObjectDefinition $objectDefinition,
		array $selections
	): void {
		if ($this->recordsToLoadForFolder->isEmpty()) {
			return;
		}

		$folderIdToRetrieve = $this->getAllFolderIds();
		if (empty($folderIdToRetrieve)) {
			return;
		}

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
			$folderIdToRetrieve,
			$this->referencedObjectDefinition
		);

		foreach ($folderIdToRetrieve as $folderId) {
			$folderRepresentation = null;
			if (array_key_exists($folderId, $recordIdToRecordCollection)) {
				$folderRepresentation = $recordIdToRecordCollection[$folderId];
			}

			$this->folderIdToFolder[$folderId] = $folderRepresentation;
		}

		$this->recordsToLoadForFolder = new RecordIdCollection();
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @return bool
	 */
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


		if ($selection instanceof FieldDefinition &&
			($selection->getName() === "folderId" || $selection->getName() === "folder_id")) {
			if (!$this->containsLoadedRecordForFolderId($objectDefinition, $currentRecordId)) {
				return true;
			}

			$fieldName = $selection->getName();
			$dbArray[$fieldName] = $this->getLoadedValueForFolderId($objectDefinition, $currentRecordId);
		}

		if ($selection instanceof RelationshipSelection && $selection->getRelationship()->getName() === "folder") {
			if (!$this->containsLoadedRecordForFolder($objectDefinition, $currentRecordId)) {
				return true;
			}

			$childRecord = $this->getLoadedValueForFolder($objectDefinition, $currentRecordId);
			$dbArray[$selection->getRelationship()->getName()] = $childRecord;
		}

		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 */
	private function containsLoadedRecordForFolderId(ObjectDefinition $objectDefinition, int $recordId): bool
	{
		return $this->loadedFolderRecordIds->containsRecordId($objectDefinition, $recordId);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return mixed|null
	 */
	private function getLoadedValueForFolderId(ObjectDefinition $objectDefinition, int $recordId)
	{
		return $this->loadedFolderRecordIds->getRecordIdValueByObjectDefinition($objectDefinition, $recordId);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 */
	private function containsLoadedRecordForFolder(ObjectDefinition $objectDefinition, int $recordId): bool
	{
		if (!$this->loadedFolderRecordIds->containsRecordId($objectDefinition, $recordId)) {
			return false;
		}

		$folderId = $this->loadedFolderRecordIds->getRecordIdValueByObjectDefinition($objectDefinition, $recordId);
		return is_null($folderId) || array_key_exists($folderId, $this->folderIdToFolder);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return mixed|null
	 */
	private function getLoadedValueForFolder(ObjectDefinition $objectDefinition, int $recordId)
	{
		$folderId = $this->loadedFolderRecordIds->getRecordIdValueByObjectDefinition($objectDefinition, $recordId);
		if (is_null($folderId)) {
			return null;
		}

		return $this->folderIdToFolder[$folderId];
	}

	/**
	 * @param QueryContext $queryContext
	 */
	private function getRecordIdToFolderIdMap(QueryContext $queryContext): void
	{
		foreach ($this->recordsToLoadForFolderId->getObjectDefinitions() as $objectDefinition) {
			$recordIds = $this->recordsToLoadForFolderId->getRecordIdsByObjectDefinition($objectDefinition);

			foreach ($recordIds as $recordId) {
				$this->loadedFolderRecordIds->addRecordIdValue($objectDefinition, $recordId);
			}
		}

		$folderManager = new FolderManager();
		$folderableObjects = [];
		$objectTypeToObjectDefinitions = [];
		foreach ($this->recordsToLoadForFolderId->getObjectDefinitions() as $objectDefinition) {
			$objectRecordIds = $this->recordsToLoadForFolderId->getRecordIdsByObjectDefinition($objectDefinition);

			if (!$folderManager->isObjectFolderable($objectDefinition->getType())) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
					"Folder Indirect Relationship called on an object that is not folderable."
				);
			}

			try {
				$objectType = FolderConstants::mapClassToObjectType($objectDefinition->getType());
			} catch (Exception $exception) {
				throw new AccessException(
					"Unable to find object type for name: {$objectDefinition->getType()}",
					0,
					$exception
				);
			}

			$objectTypeToObjectDefinitions[$objectType] = $objectDefinition;
			$folderableObjects[$objectType] = $objectRecordIds;
		}

		if (count($folderableObjects) === 0) {
			return;
		}

		// retrieve all of the folder_objects for all of the records
		$folderObjects = piFolderObjectTable::getInstance()
			->retrieveForMultipleObjectTypes($queryContext->getAccountId(), $folderableObjects);

		/** @var piFolderObject $piFolderObject */
		foreach ($folderObjects as $piFolderObject) {
			$objectDefinition = $objectTypeToObjectDefinitions[$piFolderObject->object_type];
			$this->loadedFolderRecordIds->addRecordIdValue(
				$objectDefinition,
				$piFolderObject->object_id,
				$piFolderObject->folder_id
			);
		}

		$folderObjects->free(true);
	}

	/**
	 * @return array
	 */
	private function getAllFolderIds(): array
	{
		$folderIds = [];

		foreach ($this->loadedFolderRecordIds->getObjectDefinitions() as $objectDefinition) {
			$recordIdValueMap = $this->loadedFolderRecordIds->getRecordIdValuesByObjectDefinition($objectDefinition);
			foreach ($recordIdValueMap as $recordId => $folderId) {
				if (is_null($folderId) ||
					!$this->recordsToLoadForFolder->containsRecordId($objectDefinition, $recordId) ||
					array_key_exists($folderId, $this->folderIdToFolder)
				) {
					continue;
				}

				$folderIds[] = $folderId;
			}
		}

		return array_unique($folderIds);
	}
}

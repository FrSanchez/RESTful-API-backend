<?php
namespace Api\Objects\Access;

use Api\Objects\ObjectDefinition;
use Api\Objects\RecordIdCollection;
use AbilitiesManager;
use FolderManager;
use FolderConstants;
use piFolder;
use piFolderObject;
use piFolderObjectTable;
use Exception;
use piFolderTable;
use piUserTable;

class ObjectAccessManagerImpl implements ObjectAccessManager
{
	/** @var AbilitiesManager $abilitiesManager */
	private $abilitiesManager;

	/** @var piFolderObjectTable $piFolderObjectTable */
	private $piFolderObjectTable;

	/** @var FolderManager $folderManager */
	private $folderManager;

	/**
	 * ObjectAccessManagerImpl constructor.
	 * @param AbilitiesManager $abilitiesManager
	 * @param FolderManager|null $folderManager
	 * @param piFolderObjectTable|null $piFolderObjectTable
	 *
	 */
	public function __construct(
		AbilitiesManager $abilitiesManager,
		?FolderManager $folderManager = null,
		?piFolderObjectTable $piFolderObjectTable = null
	) {
		$this->abilitiesManager = $abilitiesManager;
		$this->folderManager = $folderManager ?? new FolderManager();
		$this->piFolderObjectTable = $piFolderObjectTable ?? piFolderObjectTable::getInstance();
	}

	/**
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @return bool
	 * @throws AccessException
	 */
	public function canUserAccessObject(
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition
	): bool {
		$operationDefinition = $objectDefinition->getObjectOperationDefinitionByName('read');
		return $operationDefinition && $this->abilitiesManager->evaluateAccessRule(
			$operationDefinition->getAbilities(),
			$accessContext->getUserAbilities()
		);
	}

	/**
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 * @throws AccessException
	 */
	public function canUserAccessRecord(
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		int $recordId
	): bool {
		$recordIdCollection = new RecordIdCollection();
		$recordIdCollection->addRecordId($objectDefinition, $recordId);

		$response = $this->canUserAccessRecords($accessContext, $recordIdCollection);
		return $response->canUserAccessRecord($objectDefinition, $recordId);
	}

	/**
	 * @param AccessContext $accessContext
	 * @param RecordIdCollection $recordIds
	 * @return MultipleRecordAccessResponse
	 */
	public function canUserAccessRecords(
		AccessContext $accessContext,
		RecordIdCollection $recordIds
	): MultipleRecordAccessResponse {
		// this method filters all of the record IDs specified down to only those that are accessible
		$accessibleRecordIds = clone $recordIds;

		// remove all records where the object is not accessible by the user
		$this->filterInaccessibleObjects($accessContext, $accessibleRecordIds);

		// remove all records of folderable objects that are not accessible due to folder permissions
		$this->filterInaccessibleWithinFolder($accessContext, $accessibleRecordIds);

		// remove all folder records that are not accessible due to folder permission
		// This is being done separately since folders are not folderable
		$this->filterInaccessibleFolder($accessContext, $accessibleRecordIds);

		return new MultipleRecordAccessResponse($accessibleRecordIds);
	}

	/**
	 * @param AccessContext $accessContext
	 * @param RecordIdCollection $accessibleRecordIds
	 */
	private function filterInaccessibleObjects(
		AccessContext $accessContext,
		RecordIdCollection $accessibleRecordIds
	): void {
		foreach ($accessibleRecordIds->getObjectDefinitions() as $objectDefinition) {
			if (!$this->canUserAccessObject($accessContext, $objectDefinition)) {
				$accessibleRecordIds->removeAllByObjectDefinition($objectDefinition);
			}
		}
	}

	/**
	 * @param AccessContext $accessContext
	 * @param RecordIdCollection $accessibleRecordIds
	 */
	private function filterInaccessibleWithinFolder(
		AccessContext $accessContext,
		RecordIdCollection $accessibleRecordIds
	): void {
		// check each record to see if it's accessible
		$folderableObjects = [];
		$objectTypeToObjectDefinitions = [];
		foreach ($accessibleRecordIds->getObjectDefinitions() as $objectDefinition) {
			$objectRecordIds = $accessibleRecordIds->getRecordIdsByObjectDefinition($objectDefinition);

			if (!$this->folderManager->isObjectFolderable($objectDefinition->getType())) {
				// if the record is not folderable then it should be kept
				continue;
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

		// If no records in the collection are folderable, then all records should be accessible.
		if (count($folderableObjects) === 0) {
			return;
		}

		// Make sure that all previous cached entries are removed so that the piFolderObject and piFolder are loaded
		// from the database. This fixes an issue when code before access checking loads piFolderObject and piFolder
		// without all of the data, causing the data for folder access to be incorrect.
		piFolderObjectTable::getInstance()->getRepository()->evictAll();
		piFolderObjectTable::getInstance()->clear();
		piFolderTable::getInstance()->getRepository()->evictAll();
		piFolderTable::getInstance()->clear();

		// retrieve all of the folders and folder_objects for all of the records
		$folderObjects = piFolderObjectTable::getInstance()
			->retrieveForMultipleObjectTypes($accessContext->getAccountId(), $folderableObjects, true);

		// retrieve folders so that we can check folder access for the user.
		$piFolders = [];
		/** @var piFolderObject $piFolderObject */
		foreach ($folderObjects as $piFolderObject) {
			$piFolders[] = $piFolderObject->piFolder;
		}

		// with the folders, check if the user has access.
		/** @var piFolder $piFolder */
		$accessibleFolderIds = [];
		foreach ($piFolders as $piFolder) {
			if ($this->folderManager->hasAccessToFolder($piFolder, $accessContext->getUser())) {
				$accessibleFolderIds[$piFolder->id] = true;
			}
		}

		// filter the list of folder_object records to only those that are accessible to the user
		foreach ($folderObjects as $piFolderObject) {
			if (!isset($accessibleFolderIds[$piFolderObject->folder_id])) {
				$objectDefinition = $objectTypeToObjectDefinitions[$piFolderObject->object_type];
				$accessibleRecordIds->removeRecordId($objectDefinition, $piFolderObject->object_id);
			}
		}

		$folderObjects->free(true);
	}

	/**
	 * @param AccessContext $accessContext
	 * @param RecordIdCollection $accessibleRecordIds
	 */
	private function filterInaccessibleFolder(
		AccessContext $accessContext,
		RecordIdCollection $accessibleRecordIds
	): void {
		if (!$accessibleRecordIds->containsObjectDefinition("Folder")) {
			return;
		}

		$folderDefinition = $accessibleRecordIds->getObjectDefinitionByName("Folder");
		$folderIds = $accessibleRecordIds->getRecordIdsByObjectDefinition($folderDefinition);

		// If no records in the collection are folders, then all records should be accessible.
		if (count($folderIds) === 0) {
			return;
		}

		// retrieve folders so that we can check folder access for the user.
		$piFolders = piFolderTable::getInstance()
			->retrieveByIds($accessContext->getAccountId(), $folderIds, false);

		// with the folders, check if the user has access.
		$accessibleFolders = $this->folderManager->hasAccessToFolders($piFolders->getData(), $accessContext->getAccountId(), $accessContext->getUser());
		$accessibleFoldersMap = array_flip($accessibleFolders);
		foreach ($piFolders as $piFolder) {
			if (!isset($accessibleFoldersMap[$piFolder->id])) {
				$accessibleRecordIds->removeRecordId($folderDefinition, $piFolder->id);
			}
		}

		$piFolders->free(true);
	}
}

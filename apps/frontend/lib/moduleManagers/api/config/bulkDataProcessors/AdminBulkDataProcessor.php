<?php

namespace Api\Config\BulkDataProcessors;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\UserRepresentation;
use Api\Objects\Access\AccessException;
use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Api\Representations\RepresentationBuilderContext;
use DateTime;
use DateTimeZone;
use Doctrine_Exception;
use MyDateTime;
use stringTools;

class AdminBulkDataProcessor implements \Api\Objects\Query\BulkDataProcessor
{
	private array $recordsToLoad;
	private ObjectDefinition $referencedObjectDefinition;
	private array $adminIdToUser;

	public function __construct()
	{
		$this->recordsToLoad = [];
		$this->adminIdToUser = [];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	/**
	 * @inheritDoc
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if (is_null($doctrineRecord)) {
			return;
		}

		$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();

		if (is_null($this->referencedObjectDefinition)) {
			return;
		}

		$admin = \piUserTable::getInstance()->getFirstAdminUser((int)$objectDefinition->getAccountId());

		if (!is_null($admin)) {
			// change the index to admin id
			$this->recordsToLoad[$admin->id] = null;
		}
		$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();


	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (count($this->recordsToLoad) == 0) {
			return;
		}

		$recordSelections = array_values(BulkDataProcessorRelationshipHelper::getSelectionsForObjectDefinition(
			$selections,
			$objectDefinition,
			$this->referencedObjectDefinition,
		));

		$admin = \piUserTable::getInstance()->getFirstAdminUser((int)$queryContext->getAccountId());
		$adminAsArray = $admin->toArray(false);
		$userRepFields = [];

		foreach ($recordSelections as $fieldID => $fieldDef) {
			$fieldName = stringTools::snakeFromCamelCase($fieldDef->getName());

			switch ($fieldName) {
				case 'salesforce_id':
					$fieldName = 'crm_user_fid';
					break;
				case 'is_deleted':
					$fieldName = 'is_archived';
					break;
				case 'created_by_id':
					$fieldName = 'created_by';
					break;
				case 'updated_by_id':
					$fieldName = 'updated_by';
					break;
			}

			$fieldValue = $adminAsArray[$fieldName];

			$userRepFields[$fieldDef->getName()] = $fieldValue;
		}

		$this->adminIdToUser[$admin->id] = $userRepFields;
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

		$admin = \piUserTable::getInstance()->getFirstAdminUser((int)$objectDefinition->getAccountId());
		if (is_null($admin)) {
			$dbArray[$selection->getRelationshipName()] = null;
			return false;
		}

		if (!array_key_exists($admin->id, $this->adminIdToUser)) {
			return true;
		}

		$dbArray[$selection->getRelationshipName()] = $this->adminIdToUser[$admin->id];
		return false;
	}

}

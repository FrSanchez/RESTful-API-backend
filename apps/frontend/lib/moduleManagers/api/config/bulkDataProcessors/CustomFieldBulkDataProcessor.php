<?php
namespace Api\Config\BulkDataProcessors;

use Api\DataTypes\ArrayDataType;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use ApiFrameworkConstants;

class CustomFieldBulkDataProcessor implements BulkDataProcessor
{

	/** @var RecordIdCollection $recordsToLoad */
	private RecordIdCollection $recordsToLoad;

	/** @var array $fieldsToLoad */
	private array $fieldsToLoad;

	/** @var RecordIdValueCollection $loadedFieldValues */
	private RecordIdValueCollection $loadedFieldValues;

	/**
	 * CustomFieldBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoad = new RecordIdCollection();
		$this->fieldsToLoad = [];
		$this->loadedFieldValues = new RecordIdValueCollection();
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
		$id = $doctrineRecord ? $doctrineRecord->get(SystemColumnNames::ID) : null;
		if (is_null($id)) {
			return;
		}
		$fieldName = $selection->getName();
		if ($selection instanceof FieldDefinition && $selection->isCustom()) { // Should always be true.
			$loadedValues = $this->loadedFieldValues->getRecordIdValueByObjectDefinition($objectDefinition, $id);
			if (!$loadedValues || !array_key_exists($fieldName, $loadedValues)) {
				$this->recordsToLoad->addRecordId($objectDefinition, $id);
				$objectType = $objectDefinition->getType();
				if (!array_key_exists($objectType, $this->fieldsToLoad)) {
					$this->fieldsToLoad[$objectType] = [];
				}
				$this->fieldsToLoad[$objectType][$fieldName] = true;
			}
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinitionInput
	 * @param array $selections
	 * @param bool $allowReadReplica
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinitionInput, array $selections, bool $allowReadReplica): void
	{
		foreach ($this->recordsToLoad->getObjectDefinitions() as $objectDefinition) {
			$customFieldsSelected = array_keys($this->fieldsToLoad[$objectDefinition->getType()]);
			if (count($customFieldsSelected) == 0) {
				continue;
			}
			$customFieldProvider = $objectDefinition->getCustomFieldProvider();
			$ids = $this->recordsToLoad->getRecordIdsByObjectDefinition($objectDefinition);
			$customFieldValuesForIds = $customFieldProvider->getAdditionalFieldData($customFieldsSelected, $queryContext->getAccountId(), $queryContext->getVersion(), $ids);
			foreach ($ids as $id) {
				if (!array_key_exists($id, $customFieldValuesForIds)) {
					// The custom field provider didn't return all the IDs so add the records as needed
					$customFieldValuesForIds[$id] = [];
				}

				foreach ($customFieldsSelected as $customField) {
					if (!array_key_exists($customField, $customFieldValuesForIds[$id])) {
						// The custom field provider didn't return all the custom fields so set it to null
						$customFieldValuesForIds[$id][$customField] = null;
					}
				}
			}
			$this->loadedFieldValues->addRecordIdValues($objectDefinition, $customFieldValuesForIds);
		}

		$this->fieldsToLoad = [];
		$this->recordsToLoad = new RecordIdCollection();
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
		$id = $doctrineRecord ? $doctrineRecord->get(SystemColumnNames::ID) : null;
		if (is_null($id)) {
			return false;
		}
		$fieldName = $selection->getName();
		$loadedValues = $this->loadedFieldValues->getRecordIdValueByObjectDefinition($objectDefinition, $id);
		if (!$loadedValues || !array_key_exists($fieldName, $loadedValues)) {
			return true;
		}
		$dbArray[$fieldName] = $loadedValues[$fieldName];
		return false;
	}
}

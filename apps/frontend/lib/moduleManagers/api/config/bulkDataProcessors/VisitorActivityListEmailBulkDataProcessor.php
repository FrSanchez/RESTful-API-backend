<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipSelection;
use Doctrine_Exception;

class VisitorActivityListEmailBulkDataProcessor implements BulkDataProcessor
{
	private array $recordsToLoad;
	private array $listEmailIdToListEmail;
	private ObjectDefinition $referencedObjectDefinition;

	public function __construct()
	{
		$this->recordsToLoad = [];
		$this->listEmailIdToListEmail = [];
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
		if ($selection->getRelationshipName() === 'listEmail') {
			$queryBuilderNode
				->addSelection('email_id')
				->addSelection('piEmail', 'id')
				->addSelection('piEmail', 'list_email_id');
		}
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

		$recordId = $this->getListEmailIdFromDoctrineRecord($doctrineRecord);
		if (is_null($recordId)) {
			return;
		}

		if (!$this->containsLoadedRecordForListEmail($recordId)) {
			$this->recordsToLoad[] = $recordId;
			$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Exception
	 */
	public function fetchData(
		QueryContext $queryContext,
		ObjectDefinition $objectDefinition,
		array $selections,
		bool $allowReadReplica
	): void {
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
			$this->recordsToLoad,
			$this->referencedObjectDefinition
		);

		foreach ($this->recordsToLoad as $listEmailId) {
			$listEmailRepresentation = null;
			if (array_key_exists($listEmailId, $recordIdToRecordCollection)) {
				$listEmailRepresentation = $recordIdToRecordCollection[$listEmailId];
			}
			$this->listEmailIdToListEmail[$listEmailId] = $listEmailRepresentation;
		}
		$this->recordsToLoad = [];
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

		$currentRecordId = $this->getListEmailIdFromDoctrineRecord($doctrineRecord);
		if (is_null($currentRecordId)) {
			$dbArray[$selection->getRelationshipName()] = null;
			return false;
		}

		if (!$this->containsLoadedRecordForListEmail($currentRecordId)) {
			return true;
		}

		$dbArray[$selection->getRelationshipName()] = $this->listEmailIdToListEmail[$currentRecordId];
		return false;
	}

	/**
	 * @param int $recordId
	 * @return bool
	 */
	private function containsLoadedRecordForListEmail(int $recordId): bool
	{
		return array_key_exists($recordId, $this->listEmailIdToListEmail);
	}

	/**
	 * @param ImmutableDoctrineRecord $doctrineRecord
	 * @return string|null
	 */
	private function getListEmailIdFromDoctrineRecord(ImmutableDoctrineRecord $doctrineRecord): ?string
	{
		$relation = $doctrineRecord->reference('piEmail');
		if (!is_null($relation)) {
			return $relation->get('list_email_id');
		}
		return null;
	}
}

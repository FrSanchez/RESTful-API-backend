<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use Doctrine_Query_Exception;
use generalTools;
use piPrivacyConsentTable;
use RuntimeException;

class DoNotSellBulkDataProcessor implements BulkDataProcessor
{

	private ?piPrivacyConsentTable $piPrivacyConsentTable;

	private array $idsToLoad = [];
	private array $loadedDoNotSellRecords = [];

	const ALLOWED_OBJECTS = ['Visitor', 'Prospect'];

	public function __construct(?piPrivacyConsentTable $piPrivacyConsentTable = null)
	{
		$this->piPrivacyConsentTable = $piPrivacyConsentTable;
	}

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if (!in_array($objectDefinition->getType(), self::ALLOWED_OBJECTS)) {
			throw new RuntimeException('BulkProcessor supports: ' . implode(',', self::ALLOWED_OBJECTS));
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->idsToLoad[$doctrineRecord->get(SystemColumnNames::ID)] = false;
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 * @return void
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (empty($this->idsToLoad)) {
			return;
		}

		$objectType = ($objectDefinition->getType() === 'Prospect') ? generalTools::PROSPECT : generalTools::VISITOR;

		// fetch any do not sell records
		$doNotSellRecords = $this->getPrivacyConsentTable()->getDoNotSellRecordIdsByObjectIds(
			$queryContext->getAccountId(),
			$objectType,
			array_keys($this->idsToLoad)
		);

		foreach ($doNotSellRecords as $doNotSellRecord) {
			$this->loadedDoNotSellRecords[$doNotSellRecord] = true;
		}

		// populate any ids that do not contain do not sell records
		$this->loadedDoNotSellRecords += $this->idsToLoad;

		$this->idsToLoad = [];
	}

	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion
	): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);

		if (is_null($recordId)) {
			return false;
		}

		// record has not yet been attempted to be fetched. Load more data
		if (!array_key_exists($recordId, $this->loadedDoNotSellRecords)) {
			return true;
		}

		// modify record with loaded values
		$dbArray[$selection->getName()] = $this->loadedDoNotSellRecords[$recordId];

		return false;
	}

	protected function getPrivacyConsentTable(): piPrivacyConsentTable
	{
		if (!$this->piPrivacyConsentTable) {
			$this->piPrivacyConsentTable = piPrivacyConsentTable::getInstance();
		}

		return $this->piPrivacyConsentTable;
	}

}

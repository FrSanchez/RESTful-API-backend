<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;

class ListEmailSendOptionsBulkDataProcessor implements BulkDataProcessor
{
	public const OPERATIONAL_SELECTION = 'operationalEmail';
	public const TRACKER_DOMAIN_ID_SELECTION = 'trackerDomainId';
	private const VALID_SELECTIONS = [
		self::OPERATIONAL_SELECTION,
		self::TRACKER_DOMAIN_ID_SELECTION
	];

	private array $listEmailIdsToLoad;
	private array $loadedListEmailIds;
	private array $isOperationalFlags;
	private array $trackerDomainIds;
	private bool $loadOperationalFlags;
	private bool $loadTrackerDomainIds;
	private ?\piEmailSendOptionsTable $emailSendOptionsTable;

	public function __construct(?\piEmailSendOptionsTable $emailSendOptionsTable = null)
	{
		$this->listEmailIdsToLoad = [];
		$this->loadedListEmailIds = [];
		$this->isOperationalFlags = [];
		$this->trackerDomainIds = [];
		$this->loadOperationalFlags = false;
		$this->loadTrackerDomainIds = false;
		$this->emailSendOptionsTable = $emailSendOptionsTable;
	}

	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (!$selection instanceof FieldDefinition
			|| $objectDefinition->getType() !== 'ListEmail'
		) {
			throw new \RuntimeException('Only ListEmail records may be processed by this bulk processor');
		}

		if (!in_array($selection->getName(), self::VALID_SELECTIONS)) {
			throw new \RuntimeException('Invalid ListEmail selection given');
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->listEmailIdsToLoad[$doctrineRecord->get(SystemColumnNames::ID)] = true;

		if ($selection->getName() === self::OPERATIONAL_SELECTION) {
			$this->loadOperationalFlags = true;
		}

		if ($selection->getName() === self::TRACKER_DOMAIN_ID_SELECTION) {
			$this->loadTrackerDomainIds = true;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(
		QueryContext $queryContext,
		ObjectDefinition $objectDefinition,
		array $selections,
		bool $allowReadReplica
	): void {
		if (empty($this->listEmailIdsToLoad)) {
			return;
		}

		$emailSendOptionsTable = $this->emailSendOptionsTable ?? \piEmailSendOptionsTable::getInstance();
		$query = $emailSendOptionsTable->makeQueryToFindEmailIdsThatHaveSendOptions(
			$queryContext->getAccountId(),
			array_keys($this->listEmailIdsToLoad)
		);

		if ($this->loadOperationalFlags) {
			$query->addSelect('is_bypass_optouts');
		}

		if ($this->loadTrackerDomainIds) {
			$query->addSelect('tracker_domain_id');
		}

		$queryResults = $query->executeAndFree([], \Doctrine_Core::HYDRATE_PARDOT_FIXED_ARRAY);

		if (!empty($queryResults)) {
			foreach ($queryResults as $queryResult) {
				if ($this->loadOperationalFlags) {
					$this->isOperationalFlags[$queryResult['email_id']] = $queryResult['is_bypass_optouts'] ?? false;
				}

				if ($this->loadTrackerDomainIds) {
					$this->trackerDomainIds[$queryResult['email_id']] = $queryResult['tracker_domain_id'] ?? null;
				}
			}
		}

		$this->loadedListEmailIds += $this->listEmailIdsToLoad;
		$this->listEmailIdsToLoad = [];
	}

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

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);

		if (is_null($recordId)) {
			return false;
		}

		// Request more data to be fetched if fetchData was not previously requested for this list email record.
		if (!array_key_exists($recordId, $this->loadedListEmailIds)) {
			return true;
		}

		if ($selection->getName() === self::OPERATIONAL_SELECTION) {
			$dbArray[$selection->getName()] = $this->isOperationalFlags[$recordId] ?? false;
		}

		if ($selection->getName() === self::TRACKER_DOMAIN_ID_SELECTION) {
			$dbArray[$selection->getName()] = $this->trackerDomainIds[$recordId] ?? null;
		}

		return false;
	}
}

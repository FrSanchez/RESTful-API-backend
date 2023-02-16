<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Api\Objects\SystemFieldNames;
use sfContext;
use sfLoader;
use apiTools;
use generalTools;
use RuntimeException;
use TrackerDomainSslError;
use piTrackerDomainTable;
use SslStatusDetailsLookupManager;

class TrackerDomainSslStatusDetailFieldBulkDataProcessor implements BulkDataProcessor
{
	/** @var array $trackerDomainIdsToLoadWithSslStatus */
	private array $trackerDomainIdsToLoadWithSslStatus;

	/** @var array $trackerDomainIdToDetails */
	private array $trackerDomainIdToDetails;

	const SSL_STATUS_COLUMN = "ssl_status";

	/**
	 * TrackerDomainSslStatusDetailFieldBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->trackerDomainIdsToLoadWithSslStatus = [];
		$this->trackerDomainIdToDetails = [];
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
		$queryBuilderNode
			->addSelection(SystemFieldNames::ID)
			->addSelection(self::SSL_STATUS_COLUMN);
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

		if ($this->hasSslStatusModifierBits($doctrineRecord) && !$this->hasTrackerDomainLoaded($recordId)) {
			$this->trackerDomainIdsToLoadWithSslStatus[$recordId] = $doctrineRecord
				->get(self::SSL_STATUS_COLUMN);
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
		// Need to set the i18N folder for trackerDomain to be able to get detailed messages
		sfContext::getInstance()->getI18N()->setMessageSourceDir(
			sfLoader::getI18NDir(
				apiTools::getCamelCasedObjectNameFromId(
					generalTools::TRACKER_DOMAIN
				)
			),
			// for api calls this will always be en-US
			sfContext::getInstance()->getUser()->getCulture()
		);

		if (empty($this->trackerDomainIdsToLoadWithSslStatus)) {
			return;
		}

		$trackerDomainIdToAudit = piTrackerDomainTable::getInstance()->getLastSslFailureForMultipleTrackerDomains(
			$queryContext->getAccountId(),
			array_keys($this->trackerDomainIdsToLoadWithSslStatus)
		);

		foreach ($trackerDomainIdToAudit as $trackerDomainId => $audit) {
			$trackerDomainSslError = TrackerDomainSslError::getTrackerDomainSslErrorFromAudit($audit);
			$details = SslStatusDetailsLookupManager::getInstance()->getFailureDetailsFromTrackerDomainSslError(
				$trackerDomainSslError,
				$queryContext->getAccountId(),
				$trackerDomainId,
				$this->trackerDomainIdsToLoadWithSslStatus[$trackerDomainId]
			);
			$this->trackerDomainIdToDetails[$trackerDomainId] = $details;

		}
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

		$isError = $this->hasSslStatusModifierBits($doctrineRecord);
		if ($isError && !$this->hasTrackerDomainLoaded($currentRecordId)) {
			return true;
		}

		$details = null;
		if ($isError) {
			$details = $this->trackerDomainIdToDetails[$currentRecordId];
		} else {
			$details = SslStatusDetailsLookupManager::getInstance()->getSslStatusDetailsFromFields(
				$doctrineRecord->get(piTrackerDomainTable::FIELD_SSL_STATUS),
				$doctrineRecord->get('account_id'),
				$currentRecordId
			);
		}

		$fieldName = $selection->getName();
		$dbArray[$fieldName] = $details;
		return false;
	}

	/**
	 * @param int $trackerDomainId
	 * @return bool
	 */
	public function hasTrackerDomainLoaded(int $trackerDomainId): bool
	{
		return array_key_exists($trackerDomainId, $this->trackerDomainIdToDetails);
	}

	/**
	 * @param ImmutableDoctrineRecord $doctrineRecord
	 * @return bool
	 */
	private function hasSslStatusModifierBits(ImmutableDoctrineRecord $doctrineRecord): bool
	{
		$sslStatusValue = $doctrineRecord->get(piTrackerDomainTable::FIELD_SSL_STATUS);
		return piTrackerDomainTable::hasSslStatusModifierBits(
			$sslStatusValue,
			piTrackerDomainTable::SSL_STATUS_MODIFIER_API_ERROR
		);
	}
}

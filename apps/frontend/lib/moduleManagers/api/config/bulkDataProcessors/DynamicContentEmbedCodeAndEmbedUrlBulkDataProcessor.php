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
use Doctrine_Query_Exception;
use piDynamicContent;
use ReflectionException;

class DynamicContentEmbedCodeAndEmbedUrlBulkDataProcessor implements BulkDataProcessor
{
	const VERSION = 5;

	/** @var TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper */
	private TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper;

	/** @var int $accountId */
	private int $accountId;
	public function __construct()
	{
		$this->trackerDomainBulkDataProcessorHelper = new TrackerDomainBulkDataProcessorHelper(self::VERSION);
	}

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$this->trackerDomainBulkDataProcessorHelper->modifyPrimaryQueryBuilder(
			$objectDefinition,
			$selection,
			$queryBuilderNode
		);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @throws ReflectionException
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		$this->trackerDomainBulkDataProcessorHelper->checkAndAddRecordToLoadIfNeedsLoading($objectDefinition, $selection, $doctrineRecord);
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
		$this->accountId = $queryContext->getAccountId();
		$this->trackerDomainBulkDataProcessorHelper->fetchData($queryContext);

	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}
		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);
		if (is_null($recordId)) {
			return false;
		}

		if (!is_null($trackerDomainId) && !$this->trackerDomainBulkDataProcessorHelper->containsLoadedRecordForTrackerDomain($trackerDomainId)) {
			return true;
		}

		$domain = $this->trackerDomainBulkDataProcessorHelper->getTrackerDomain($trackerDomainId);

		$embedUrl = piDynamicContent::formEmbedUrl($domain, $this->accountId, $recordId);
		if ($selection->getName() == 'embedUrl' || $selection->getName() == 'embed_url') {
			$dbArray[$selection->getName()] = $embedUrl;
		}
		if ($selection->getName() == 'embedCode' || $selection->getName() == 'embed_code') {
			$embedCode = piDynamicContent::formEmbedCodeFromEmbedUrl($embedUrl);
			$dbArray[$selection->getName()] = $embedCode;
		}

		return false;

	}
}

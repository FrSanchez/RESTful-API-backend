<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\Relationships\RelationshipSelection;
use ReflectionException;
use Doctrine_Query_Exception;

class TrackerAndTrackerDomainBulkDataProcessor implements BulkDataProcessor
{
	const VERSION = 5;

	/** @var TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper */
	private TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper;

	/** @var TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper */
	private TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper;

	/** @var VanityUrlBulkDataProcessorHelper $vanityUrlBulkDataProcessorHelper */
	private VanityUrlBulkDataProcessorHelper $vanityUrlBulkDataProcessorHelper;

	public function __construct()
	{
		$this->trackerBulkDataProcessorHelper = new TrackerBulkDataProcessorHelper(self::VERSION);
		$this->trackerDomainBulkDataProcessorHelper = new TrackerDomainBulkDataProcessorHelper(self::VERSION);
		$this->vanityUrlBulkDataProcessorHelper = new VanityUrlBulkDataProcessorHelper(self::VERSION);
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
		$this->trackerBulkDataProcessorHelper->modifyPrimaryQueryBuilder(
			$objectDefinition,
			$selection,
			$queryBuilderNode
		);

		$this->trackerDomainBulkDataProcessorHelper->modifyPrimaryQueryBuilder(
			$objectDefinition,
			$selection,
			$queryBuilderNode
		);

		$this->vanityUrlBulkDataProcessorHelper->modifyPrimaryQueryBuilder(
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
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		$this->trackerBulkDataProcessorHelper->checkAndAddRecordToLoadIfNeedsLoading(
			$objectDefinition,
			$selection,
			$doctrineRecord
		);

		$this->trackerDomainBulkDataProcessorHelper->checkAndAddRecordToLoadIfNeedsLoading(
			$objectDefinition,
			$selection,
			$doctrineRecord
		);

		$this->vanityUrlBulkDataProcessorHelper->checkAndAddRecordToLoadIfNeedsLoading(
			$objectDefinition,
			$selection,
			$doctrineRecord
		);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		$this->trackerBulkDataProcessorHelper->fetchData($queryContext);
		$this->trackerDomainBulkDataProcessorHelper->fetchData($queryContext);
		$this->vanityUrlBulkDataProcessorHelper->fetchData($queryContext);
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
		if ($this->trackerBulkDataProcessorHelper->shouldModifyRecord($objectDefinition, $selection)) {
			return $this->trackerBulkDataProcessorHelper->modifyRecord(
				$objectDefinition,
				$selection,
				$doctrineRecord,
				$dbArray,
				$apiVersion
			);
		}

		if ($this->trackerDomainBulkDataProcessorHelper->shouldModifyRecord($objectDefinition, $selection)) {
			return $this->trackerDomainBulkDataProcessorHelper->modifyRecord(
				$objectDefinition,
				$selection,
				$doctrineRecord,
				$dbArray,
				$apiVersion,
				$this->trackerBulkDataProcessorHelper
			);
		}

		if ($this->vanityUrlBulkDataProcessorHelper->shouldModifyRecord($objectDefinition, $selection)) {
			return $this->vanityUrlBulkDataProcessorHelper->modifyRecord(
				$objectDefinition,
				$selection,
				$doctrineRecord,
				$dbArray,
				$apiVersion,
				$this->trackerDomainBulkDataProcessorHelper,
				$this->trackerBulkDataProcessorHelper
			);
		}

		return false;
	}
}

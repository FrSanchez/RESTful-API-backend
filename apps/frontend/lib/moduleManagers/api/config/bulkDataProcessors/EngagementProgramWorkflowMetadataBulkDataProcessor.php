<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use Doctrine_Query_Exception;
use EngagementStudioLoopingAdoptionMetricsManager;
use piWorkflowMetadataTable;
use RuntimeException;

class EngagementProgramWorkflowMetadataBulkDataProcessor implements BulkDataProcessor
{
	public const BUSINESS_HOURS = 'businessHours';
	public const TIMEZONE = 'timezone';
	public const PROSPECTS_MULTIPLE_ENTRY = 'prospectsMultipleEntry';

	public const WORKFLOW_METADATA_NAMES = [
		self::BUSINESS_HOURS => piWorkflowMetadataTable::METADATA_BUSINESS_HOURS,
		self::TIMEZONE => piWorkflowMetadataTable::METADATA_TIMEZONE,
		self::PROSPECTS_MULTIPLE_ENTRY => piWorkflowMetadataTable::METADATA_LOOPING
	];

	private array $workflowIds;
	private array $workflowMetadata;
	private array $metadataNames;

	/**
	 * WorkflowMetadataBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->workflowIds = [];
		$this->workflowMetadata = [];
		$this->metadataNames = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param $selection
	 * @param QueryBuilderNode $queryBuilderNode
	 * @return void
	 */
	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @return void
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if ($objectDefinition->getType() !== 'EngagementProgram' || !array_key_exists($selection->getName(), self::WORKFLOW_METADATA_NAMES)) {
			throw new RuntimeException('BulkDataProcessor requires an EngagementProgram with fields:' . implode(array_keys(self::WORKFLOW_METADATA_NAMES)));
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->workflowIds[(int)$doctrineRecord->get(SystemColumnNames::ID)] = null;
		$this->metadataNames[self::WORKFLOW_METADATA_NAMES[$selection->getName()]] = null;
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
		if (empty($this->workflowIds)) {
			return;
		}

		$workflowMetadata = piWorkflowMetadataTable::getInstance()->retrieveSelectedValuesForWorkflowIds(
			$queryContext->getAccountId(),
			array_keys($this->workflowIds),
			array_keys($this->metadataNames)
		);

		if (empty($workflowMetadata)) {
			return;
		}

		foreach ($workflowMetadata as $record) {
			$this->workflowMetadata[(int)$record['workflow_id']][$record['name']] = $record['metadata'];
		}

		$this->workflowIds = [];
		$this->metadataNames = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @return bool
	 */
	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord) || empty($this->workflowMetadata)) {
			return false;
		}

		$workflowId = (int)$doctrineRecord->get(SystemColumnNames::ID);
		if (!isset($this->workflowMetadata[$workflowId])) {
			return false;
		}

		if ($selection->getName() === self::TIMEZONE) {
			$dbArray[self::TIMEZONE] = $this->workflowMetadata[$workflowId][piWorkflowMetadataTable::METADATA_TIMEZONE] ?? null;
		} elseif ($selection->getName() === self::BUSINESS_HOURS) {
			$dbArray[self::BUSINESS_HOURS] = $this->getBusinessHours($workflowId);
		} elseif ($selection->getName() === self::PROSPECTS_MULTIPLE_ENTRY) {
			$dbArray[self::PROSPECTS_MULTIPLE_ENTRY] = $this->getProspectsMultipleEntry($workflowId);
		}

		return false;
	}

	/**
	 * parses business_hours entry and assigns corresponding values
	 * @param int $workflowId
	 * @return array|null
	 */
	private function getBusinessHours(int $workflowId): ?array
	{
		// if business hours is not set, return null instead of a businessHoursRepresentation of nulls
		if (!isset($this->workflowMetadata[$workflowId][piWorkflowMetadataTable::METADATA_BUSINESS_HOURS])) {
			return null;
		}

		$businessHours = json_decode(
			$this->workflowMetadata[$workflowId][piWorkflowMetadataTable::METADATA_BUSINESS_HOURS],
			true
		);

		$days = [];
		foreach ($businessHours['include']['weekly'] as $day => $times) {
			$days[] = $day;
			$startTime = $times[0]['start'];
			$endTime = $times[0]['end'];
		}

		return [
			'days' => $days ?? null,
			'startTime' => $startTime ?? null,
			'endTime' => $endTime ?? null
		];
	}

	/**
	 * parses looping_metadata entry and assigns corresponding values
	 * @param int $workflowId
	 * @return array|null
	 */
	private function getProspectsMultipleEntry(int $workflowId): ?array
	{
		if (!isset($this->workflowMetadata[$workflowId][piWorkflowMetadataTable::METADATA_LOOPING])) {
			return null;
		}

		$loopingMetadata = json_decode(
			$this->workflowMetadata[$workflowId][piWorkflowMetadataTable::METADATA_LOOPING],
			true
		);

		$minimumDuration = $loopingMetadata[EngagementStudioLoopingAdoptionMetricsManager::METADATA_MINIMUM_DURATION] ?? null;
		$maxEntries = $loopingMetadata[EngagementStudioLoopingAdoptionMetricsManager::METADATA_MAX_ENTRIES] ?? null;

		if ($maxEntries == -1) {
			$maxEntries = null;
		}

		return [
			'minimumDurationInDays' => $minimumDuration,
			'maximumEntries' => $maxEntries
		];
	}

	/**
	 * Returns an array indexed by workflow_ids
	 * @param array $workflowMetadata
	 * @return void
	 */
	private function extractMetadata(array $workflowMetadata): void
	{
		if (empty($workflowMetadata)) {
			return;
		}

		foreach ($workflowMetadata as $record) {
			$this->workflowMetadata[(int)$record['workflow_id']][$record['name']] = $record['metadata'];
		}
	}
}

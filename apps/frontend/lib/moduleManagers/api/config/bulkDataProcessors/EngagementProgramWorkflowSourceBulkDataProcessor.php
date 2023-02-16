<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use Doctrine_Query_Exception;
use piWorkflowSourceTable;
use RuntimeException;

class EngagementProgramWorkflowSourceBulkDataProcessor implements BulkDataProcessor
{
	public const SOURCE_LIST_IDS = 'sourceListIds';
	public const SUPPRESSION_LIST_IDS = 'suppressionListIds';

	private array $workflowIds;
	private array $workflowSource;

	/**
	 * WorkflowSourceBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->workflowIds = [];
		$this->workflowSource = [];
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
		if ($objectDefinition->getType() !== 'EngagementProgram' || !in_array($selection->getName(), [self::SOURCE_LIST_IDS, self::SUPPRESSION_LIST_IDS])) {
			throw new RuntimeException('BulkDataProcessor requires an EngagementProgram with fields: ' . implode([self::SOURCE_LIST_IDS, self::SUPPRESSION_LIST_IDS]));
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->workflowIds[(int)$doctrineRecord->get(SystemColumnNames::ID)] = null;
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

		$workflowSource = piWorkflowSourceTable::getInstance()->retrieveAllListIdsForWorkflowIds(
			$queryContext->getAccountId(),
			array_keys($this->workflowIds)
		);

		if (empty($workflowSource)) {
			return;
		}

		foreach ($workflowSource as $record) {
			$name = $record['is_suppressed'] ? self::SUPPRESSION_LIST_IDS : self::SOURCE_LIST_IDS;
			$this->workflowSource[(int)$record['workflow_id']][$name][] = (int)$record['listx_id'];
		}

		$this->workflowIds = [];
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
		if (is_null($doctrineRecord) || empty($this->workflowSource)) {
			return false;
		}

		$workflowId = (int)$doctrineRecord->get(SystemColumnNames::ID);
		if (!isset($this->workflowSource[$workflowId])) {
			return false;
		}

		if ($selection->getName() === self::SOURCE_LIST_IDS) {
			$dbArray[self::SOURCE_LIST_IDS] = $this->workflowSource[$workflowId][self::SOURCE_LIST_IDS];
		} elseif ($selection->getName() === self::SUPPRESSION_LIST_IDS) {
			$dbArray[self::SUPPRESSION_LIST_IDS] = $this->workflowSource[$workflowId][self::SUPPRESSION_LIST_IDS];
		}

		return false;
	}
}

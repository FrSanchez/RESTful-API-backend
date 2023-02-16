<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use Doctrine_Collection;
use Hostname;
use Pardot\Constants\ShardDb\Export\StatusConstants as ExportStatusConstants;
use piExportFile;
use piExportFileTable;
use RuntimeException;

class ExportFilesBulkDataProcessor implements BulkDataProcessor
{
	private const IS_EXPIRED = 'is_expired';
	private const STATUS = 'status';

	private ?Doctrine_Collection $exportFiles;
	protected ?int $exportId;
	private ?piExportFileTable $piExportFileTable;

	public function __construct($piExportFileTable = null)
	{
		$this->exportId = null;
		$this->exportFiles = null;
		$this->piExportFileTable = $piExportFileTable;
	}

	/**
	 * @inheritDoc
	 */
	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::ID)
			->addSelection(self::STATUS)
			->addSelection(self::IS_EXPIRED);
	}

	/**
	 * @inheritDoc
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if ($objectDefinition->getType() !== 'Export' || $selection->getName() !== 'resultRefs') {
			throw new RuntimeException('BulkDataProcessor requires an Export with field resultRefs');
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		//check export status
		$status = $doctrineRecord->get(self::STATUS);
		$isExpired = $doctrineRecord->get(self::IS_EXPIRED);
		if ($status != ExportStatusConstants::COMPLETE || $isExpired) {
			return;
		}

		$this->exportId = $doctrineRecord->get(SystemColumnNames::ID);
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (is_null($this->exportId)) {
			return;
		}

		if (is_null($this->exportFiles)) {
			$this->exportFiles = $this->getPiExportFileTable()
				->getExportFilesForExportId($this->exportId, $queryContext->getAccountId());
		}

		$this->exportId = null;
	}

	/**
	 * @inheritDoc
	 */
	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord) || is_null($this->exportFiles)) {
			return false;
		}

		$resultRefs = [];
		/** @var piExportFile $exportFile */
		foreach ($this->exportFiles as $exportFile) {
			$resultRefs[] = $this->getResultRefPath($apiVersion, $exportFile->export_id, $exportFile->id);
		}
		$dbArray[$selection->getName()] = $resultRefs;

		$this->exportFiles = null;

		return false;
	}

	/**
	 * @param int $apiVersion
	 * @param int $exportId
	 * @param int $exportFileId
	 * @return string
	 */
	private function getResultRefPath(int $apiVersion, int $exportId, int $exportFileId): string
	{
		$path = "/api/v" . $apiVersion . "/exports/" . $exportId . "/results/" . $exportFileId;
		return 'https://' . Hostname::getAppHostname() . $path;
	}

	/**
	 * @return piExportFileTable
	 */
	private function getPiExportFileTable(): piExportFileTable
	{
		if (is_null($this->piExportFileTable)) {
			$this->piExportFileTable = piExportFileTable::getInstance();
		}
		return $this->piExportFileTable;
	}
}

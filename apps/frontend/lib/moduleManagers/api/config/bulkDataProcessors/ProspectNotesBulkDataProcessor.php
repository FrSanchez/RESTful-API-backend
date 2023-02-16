<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Query\QueryContext;
use Doctrine;
use Doctrine_Query;

class ProspectNotesBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = ["notes"];

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$query = Doctrine_Query::create();
		$query->select('notes, prospect_id')
			->from('piProspectAnalysis INDEXBY prospect_id')
			->where('account_id = ?', [$queryContext->getAccountId()])
			->andWhereIn('prospect_id', array_keys($prospectIdsToLoad));
		if ($allowReadReplica) {
			$query->readReplicaSafe();
		}
		$fetchedData = $query->executeAndFree([], Doctrine::HYDRATE_PARDOT_INDEXED_SINGLE_SCALAR);
		$this->fetchedData += $fetchedData;
	}

	protected function getDbValue(int $recordId, $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		return $this->fetchedData[$recordId] ?? '';
	}
}

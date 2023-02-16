<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Core;
use Doctrine_Query;
use AutomationProcessingUtility;
use piVisitorActivityTable;

class ProspectFirstActivityAtBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = ["first_activity_at", "firstActivityAt"];
	const FIELD_FIRST_ACTIVITY_AT = "first_activity_at";

	protected function addFieldSelectionToPrimaryQuery(QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(self::FIELD_FIRST_ACTIVITY_AT);
	}

	protected function addRecordToLoadIfNeedsLoading(int $recordId, ImmutableDoctrineRecord $doctrineRecord, $value = true) : bool
	{
		$needsLoading = false;
		if ($doctrineRecord->get(self::FIELD_FIRST_ACTIVITY_AT) == null) {
			$needsLoading = parent::addRecordToLoadIfNeedsLoading($recordId, $doctrineRecord, $value);
		}
		return $needsLoading;
	}

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$accountId = $queryContext->getAccountId();
		$filteredProspectIds = array_keys($prospectIdsToLoad);
		$util = new AutomationProcessingUtility();
		$filteredProspectIds = $util->filterBannedProspects($accountId, $filteredProspectIds, true);

		$query = Doctrine_Query::create();
		$query->select('MIN(created_at), prospect_id')
			->from('piVisitorActivity INDEXBY prospect_id')
			->where('account_id = ?', $accountId)
			->andWhereIn('prospect_id', $filteredProspectIds)
			->andWhereNotIn('type', piVisitorActivityTable::getInstance()->nonVisitorActivityTypes())
			->groupBy('prospect_id');
		if ($allowReadReplica) {
			$query->readReplicaSafe();
		}
		$fetchedData = $query->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_INDEXED_SINGLE_SCALAR);
		$this->fetchedData += $fetchedData;

		$query = Doctrine_Query::create();
		$query->select('vpv.created_at, v.prospect_id')
			->from('piVisitor v')
			->innerJoin('v.piVisitorPageViews vpv')
			->leftJoin('v.piVisitorPageViews vpv2 ON (v.id = vpv2.visitor_id AND vpv2.account_id = ? AND (vpv.id > vpv2.id))', $accountId)
			->where('v.account_id = ?', $accountId)
			->andWhere('v.is_archived = ?', false)
			->andWhere('vpv2.id IS NULL')
			->andWhereIn('v.prospect_id', $filteredProspectIds);
		if ($allowReadReplica) {
			$query->readReplicaSafe();
		}
		$queryResults = $query->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_FIXED_ARRAY);

		foreach ($queryResults as $queryResult) {
			$prospectId = $queryResult['prospect_id'];
			$createdAt1 = $this->fetchedData[$prospectId] ?? null;
			$createdAt2 = $queryResult['_created_at'];
			if (is_null($createdAt1) && !is_null($createdAt2)) {
				$this->fetchedData[$prospectId] = $createdAt2;
			} elseif (!is_null($createdAt2) && (strtotime($createdAt2) < strtotime($createdAt1))) {
				$this->fetchedData[$prospectId] = $createdAt2;
			}
		}
	}

	protected function getDbValue(int $recordId, FieldDefinition $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$dbValue = $doctrineRecord->get(self::FIELD_FIRST_ACTIVITY_AT);
		if (is_null($dbValue)) {
			$dbValue = $this->fetchedData[$recordId] ?? null;
		}
		return $dbValue;
	}
}

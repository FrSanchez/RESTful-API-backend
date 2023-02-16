<?php
namespace Api\Config\BulkDataProcessors;

use AccountSettingsConstants;
use AccountSettingsManager;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\Query\QueryContext;
use Doctrine_Core;
use Doctrine_Query;
use ProspectConstants;

class ProspectGradeBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = ["grade"];
	const FIELD_GRADE = "grade";
	const FIELD_PROFILE_ID = "profile_id";

	protected function addFieldSelectionToPrimaryQuery(QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(self::FIELD_GRADE);
		$queryBuilderNode->addSelection(self::FIELD_PROFILE_ID);
	}

	protected function addRecordToLoadIfNeedsLoading(int $recordId, ImmutableDoctrineRecord $doctrineRecord, $value = true) : bool
	{
		$needsLoading = false;
		if ($doctrineRecord->get(self::FIELD_GRADE) == ProspectConstants::GRADE_D) {
			$needsLoading = parent::addRecordToLoadIfNeedsLoading(
				$recordId,
				$doctrineRecord,
				$doctrineRecord->get(self::FIELD_PROFILE_ID)
			);
		}
		return $needsLoading;
	}

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$accountId = $queryContext->getAccountId();
		$accountSettingsManager = AccountSettingsManager::getInstance($accountId);
		$useHavingClause = $accountSettingsManager->isFlagEnabled(AccountSettingsConstants::FEATURE_ENABLE_HAVING_CLAUSE_IN_PROSPECT_GRADE_BULK_DATA_PROCESSOR_QUERY);
		$filteredData = $prospectIdsToLoad; // shallow copy array
		if ($useHavingClause) {
			$query = Doctrine_Query::create()
				->select('pc.prospect_id, c.profile_id, c.is_archived')
				->from('piProfileCriteriaProspect pc')
				->innerJoin('pc.piProfileCriteria c')
				->where('pc.account_id = ?', [$accountId])
				->andWhereIn('pc.prospect_id', array_keys($filteredData))
				->groupBy('pc.prospect_id, c.profile_id, c.is_archived')
				->having('c.is_archived = 0');
			if ($allowReadReplica) {
				$query->readReplicaSafe();
			}
		} else {
			$query = Doctrine_Query::create()
				->select('pc.prospect_id, c.profile_id')
				->from('piProfileCriteriaProspect pc')
				->innerJoin('pc.piProfileCriteria c')
				->where('pc.account_id = ?', [$accountId])
				->andWhereIn('pc.prospect_id', array_keys($filteredData))
				->andWhere('c.is_archived = 0')
				->groupBy('pc.prospect_id, c.profile_id');
			if ($allowReadReplica) {
				$query->readReplicaSafe();
			}
		}
		$queryResults = $query->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_FIXED_ARRAY);
		foreach ($queryResults as $queryResult) {
			// unset entries in $filteredData for prospects that should have a grade.
			// if query result matches prospect/profile from primary query results OR prospect_id matches but
			// profile_id is NULL in both prospect and profile_criteria table, then prospect should have a grade.
			$pid = $queryResult['prospect_id'];
			if (array_key_exists($pid, $filteredData) && $filteredData[$pid] == $queryResult['_profile_id']) {
				unset($filteredData[$pid]);
			}
		}
		$this->fetchedData += $filteredData;
	}

	protected function getDbValue(int $recordId, $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$dbValue = $doctrineRecord->get(self::FIELD_GRADE);
		if ($dbValue == ProspectConstants::GRADE_D &&
			array_key_exists($recordId, $this->fetchedData)) {
			$dbValue = null;
		} else {
			$dbValue = $this->getDisplayGrade($dbValue);
		}
		return $dbValue;
	}

	private function getDisplayGrade($gradeNo): ?string
	{
		if ($gradeNo === null) {
			return null;
		}
		return ProspectConstants::getGradeName($gradeNo);
	}
}

<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Core;
use Doctrine_Query;
use AutomationProcessingUtility;

class ProspectGoogleAnalyticsParamsBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = [
		"campaign_parameter", "campaignParameter",
		"medium_parameter", "mediumParameter",
		"source_parameter", "sourceParameter",
		"content_parameter", "contentParameter",
		"term_parameter", "termParameter"
	];

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$select = "v.id, v.prospect_id";
		$separator = ", ";
		$query = Doctrine_Query::create();
		$accountId = $queryContext->getAccountId();
		$filteredProspectIds = array_keys($prospectIdsToLoad);
		$util = new AutomationProcessingUtility();

		// WARNING! This logic must remain in sync with queries performed by SalesforceConnector::getSpecialValue()
		$filteredProspectIds = $util->filterBannedProspects($accountId, $filteredProspectIds, true);
		$whereConditionEarliestVisitor =
			"v.id = (
				SELECT MIN(id)
				FROM piVisitor
				WHERE prospect_id = v.prospect_id AND is_archived = 0 AND is_filtered = 0 AND account_id = ?
			)";

		if ($this->isFieldSelected("campaignParameter") || $this->isFieldSelected("campaign_parameter")) {
			$select .= $separator . "v.campaign_parameter AS campaign";
		}
		if ($this->isFieldSelected("mediumParameter") || $this->isFieldSelected("medium_parameter")) {
			$select .= $separator . "v.medium_parameter AS medium";
		}
		if ($this->isFieldSelected("sourceParameter") || $this->isFieldSelected("source_parameter")) {
			$select .= $separator . "v.source_parameter AS source";
		}
		if ($this->isFieldSelected("contentParameter") || $this->isFieldSelected("content_parameter")) {
			$select .= $separator . "v.content_parameter AS content";
		}
		if ($this->isFieldSelected("termParameter") || $this->isFieldSelected("term_parameter")) {
			$select .= $separator . "v.term_parameter AS term";
		}

		$query->select($select)
			->from('piVisitor v')
			->where('v.account_id = ?', [$accountId])
			->andWhere($whereConditionEarliestVisitor, [$accountId])
			->andWhereIn('v.prospect_id', $filteredProspectIds);
		if ($allowReadReplica) {
			$query->readReplicaSafe();
		}
		$queryResults = $query->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_OBJECT_ARRAY);

		foreach ($queryResults as $queryResult) {
			$fieldValues = [];
			$fieldValues["campaignParameter"] = $queryResult['campaign'] ?? null;
			$fieldValues["mediumParameter"] = $queryResult['medium'] ?? null;
			$fieldValues["sourceParameter"] = $queryResult['source'] ?? null;
			$fieldValues["contentParameter"] = $queryResult['content'] ?? null;
			$fieldValues["termParameter"] = $queryResult['term'] ?? null;
			$this->fetchedData[$queryResult['prospect_id']] = $fieldValues;
		}
	}

	protected function getDbValue(int $recordId, FieldDefinition $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$arrayOfDbValues = $this->fetchedData[$recordId] ?? [];
		if (count($arrayOfDbValues) == 0) {
			return null;
		}
		switch ($selection->getName()) {
			case 'campaign_parameter':
			case 'campaignParameter':
				return $arrayOfDbValues['campaignParameter'];
			case 'medium_parameter':
			case 'mediumParameter':
				return $arrayOfDbValues['mediumParameter'];
			case 'source_parameter':
			case 'sourceParameter':
				return $arrayOfDbValues['sourceParameter'];
			case 'content_parameter':
			case 'contentParameter':
				return $arrayOfDbValues['contentParameter'];
			case 'term_parameter':
			case 'termParameter':
				return $arrayOfDbValues['termParameter'];
			default:
				return null;
		}
	}
}

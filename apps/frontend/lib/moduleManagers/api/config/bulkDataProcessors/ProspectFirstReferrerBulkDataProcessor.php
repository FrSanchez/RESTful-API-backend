<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Collection;
use Doctrine_Query;
use VisitorReferrerPeer;

class ProspectFirstReferrerBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = [
		"first_referrer_url", "firstReferrerUrl",
		"first_referrer_type", "firstReferrerType",
		"first_referrer_query", "firstReferrerQuery",
	];

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$isTypeSelected = false;
		$select = "vr.id, vr.prospect_id";
		$separator = ", ";
		$query = Doctrine_Query::create();

		if ($this->isFieldSelected("firstReferrerQuery") || $this->isFieldSelected("first_referrer_query") ||
			$this->isFieldSelected("firstReferrerType") || $this->isFieldSelected("first_referrer_type")) {
			$select .= $separator . "vr.query";
		}
		if ($this->isFieldSelected("firstReferrerType") || $this->isFieldSelected("first_referrer_type")) {
			$select .= $separator . "vr.type" . $separator . "vr.vendor";
			$isTypeSelected = true;
		}
		if ($this->isFieldSelected("firstReferrerUrl") || $this->isFieldSelected("first_referrer_url")) {
			$select .= $separator . "vr.referrer";
		}

		$accountId = $queryContext->getAccountId();
		// NOTE: This query logic deviates slightly from VisitorReferrerPeer::getFirstNonNullVisitorReferrer that is
		// used by SalesforceConnector::getSpecialValue() for syncing prospect proprietary fields to Salesforce in that
		// it does not perform secondary query to find records with NULL referrer when non-NULL referrer not found.
		// This secondary query was deemed unnecessary because logic that populates visitor referrer in DB will not
		// populate the other visitor referrer fields in DB record if referrer url is not available.
		$query->select($select)
			->from('piVisitorReferrer vr')
			->where('vr.account_id = ?', [$accountId])
			->andWhere('vr.id = (SELECT MIN(id) FROM piVisitorReferrer WHERE referrer IS NOT NULL AND prospect_id = vr.prospect_id AND account_id = ?)', $accountId)
			->andWhereIn('vr.prospect_id', array_keys($prospectIdsToLoad));
		if($allowReadReplica) {
			$query->readReplicaSafe();
		}
		$queryResults = $query->executeAndFree();

		foreach ($queryResults as $queryResult) {
			$fieldValues = [];
			$fieldValues["firstReferrerUrl"] = $queryResult['referrer'] ?? null;
			$firstReferrerQuery = $queryResult['query'] ?? null;
			$fieldValues["firstReferrerQuery"] = $firstReferrerQuery;
			if ($isTypeSelected) {
				$fieldValues["firstReferrerType"] = VisitorReferrerPeer::getFirstVisitorReferrerTypeFromParts(
					$queryResult['type'] ?? null,
					$firstReferrerQuery,
					$queryResult['vendor'] ?? null
				);
			}
			$this->fetchedData[$queryResult['prospect_id']] = $fieldValues;
		}
		if ($queryResults instanceof Doctrine_Collection) {
			$queryResults->free(true);
		}
	}

	protected function getDbValue(int $recordId, FieldDefinition $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$arrayOfDbValues = $this->fetchedData[$recordId] ?? [];
		if (count($arrayOfDbValues) == 0) {
			return null;
		}

		switch ($selection->getName()) {
			case 'first_referrer_url':
			case 'firstReferrerUrl':
				return $arrayOfDbValues['firstReferrerUrl'];
			case 'first_referrer_type':
			case 'firstReferrerType':
				return $arrayOfDbValues['firstReferrerType'];
			case 'first_referrer_query':
			case 'firstReferrerQuery':
				return $arrayOfDbValues['firstReferrerQuery'];
			default:
				return null;
		}
	}
}

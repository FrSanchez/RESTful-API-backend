<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Pardot\WebAnalytics\WebAnalyticsTrackingCode;
use VisitorFirstPartyFlags;

/**
 * Implementing a bulk processor for a derived field, where the data is calculated, but not retrieved from the DB
 * Class TrackingCodeBulkDataProcessor
 * @package Api\Config\BulkDataProcessors
 */
class TrackingCodeBulkDataProcessor implements BulkDataProcessor
{
	const DEFAULT_CAMPAIGN_ID_COLUMN = "default_campaign_id";
	const DOMAIN_COLUMN = "domain";
	/** @var int */
	private $accountId;
	/** @var bool */
	private $isFirstPartyTrackingEnabled;

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		// first make sure the required columns are retrieved from the DB
		$queryBuilderNode
			->addSelection(self::DEFAULT_CAMPAIGN_ID_COLUMN)
			->addSelection(self::DOMAIN_COLUMN);
	}

	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		// No more data is needed, but caching the values required to fulfill it
		$this->accountId = $queryContext->getAccountId();
		$this->isFirstPartyTrackingEnabled = VisitorFirstPartyFlags::isFirstPartyTrackingEnabled($this->accountId);
	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$recordAsArray, int $apiVersion): bool
	{
		$trackingCode = null;
		$defaultCampaignId = $doctrineRecord->get('default_campaign_id');
		$domain = $doctrineRecord->get('domain');
		// The tracking Code should only be returned when:
		// 1. First-Party is enabled via the account setting
		// 2. The tracker domain was updated with a default campaign.
		//    In this case the tracker code will have a blank value and Pardot will add the default campaign configured when we receive a request using that code
		if ($this->isFirstPartyTrackingEnabled && !is_null($defaultCampaignId) && !is_null($domain)) {
			// we made sure defaultCampaignId is set, but we send a blank value to skip the field, and let the server take care of it
			$trackingCode = WebAnalyticsTrackingCode::getTrackingCode($this->accountId + 1e3, null, $domain);
			$openScriptTag = "<script type='text/javascript'>\n";
			$closeScriptTag = "\n</script>";
			$trackingCode = $openScriptTag . $trackingCode . $closeScriptTag;
		}
		$fieldName = $selection->getName();
		$recordAsArray[$fieldName] = $trackingCode;
		// let the engine know all data has been processed
		return false;
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		// No extra processing is needed for this processor
	}
}

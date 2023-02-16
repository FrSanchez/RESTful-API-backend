<?php
namespace Api\Config\Objects\Campaign\RecordActions;

use Api\Config\Objects\Campaign\Gen\RecordActions\AbstractCampaignConnectSalesforceCampaignAction;
use Api\Exceptions\ApiException;
use Api\Objects\Access\AccessContext;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\SingleResultQuery;
use Api\Objects\RecordActions\RecordActionContext;
use Api\Gen\Representations\CampaignRepresentation;
use ApiErrorLibrary;
use CampaignConnectManager;
use CRMManager;
use Exception;
use piAccount;
use piAccountTable;
use piCampaignTable;
use piCrmCampaignTable;
use RESTClient;
use RuntimeException;
use SalesforceConnector;
use sfContext;

class ConnectSalesforceCampaignRecordAction extends AbstractCampaignConnectSalesforceCampaignAction
{

	/**
	 * Validates the arguments for the Connected Campaign Record Action
	 *
	 * @param RecordActionContext $recordActionContext
	 * @param string $salesforceCampaignId
	 * @throws Exception
	 */
	public function validateWithArgs(RecordActionContext $recordActionContext, string $salesforceCampaignId): void
	{
		$accountId = $recordActionContext->getAccountId();
		$pardotCampaignId = $recordActionContext->getRecordId();
		$campaignAlignmentManager = new CampaignConnectManager($accountId);

		$campaignAlignmentManager->validate($pardotCampaignId, $salesforceCampaignId);
	}

	public function executeActionWithArgs(RecordActionContext $recordActionContext, string $salesforceCampaignId): CampaignRepresentation
	{
		$accountId = $recordActionContext->getAccountId();
		$campaignId = $recordActionContext->getRecordId();

		// Retrieve the Pardot Campaign
		$pardotCampaign = $this->getPiCampaignTable()
			->retrieveByIds(
				$campaignId,
				$accountId,
				false
			);

		if(!$pardotCampaign) {
			// This should not fail. Verified in validation function.
			throw new RuntimeException('Failed to find Pardot Campaign with ID: ' . $campaignId);
		}

		// We have validated the connector at this point.
		$account = $this->getPiAccountTable()->retrieveById($accountId);
		$crmManager = $this->getCRMManager($account);
		$crmManager->initializeConnector(false);

		/* @var $crmConnector SalesforceConnector */
		$connector = $crmManager->getConnector();

		if(!$connector) {
			// This should not fail. Verified in validation function.
			throw new RuntimeException('Failed to retrieve connector');
		}

		// Retrieve the CRM Campaign
		$salesforceCampaign = $this->getCrmCampaignTable()
			->getCrmCampaignByCrmFid(
				$recordActionContext->getAccountId(),
				$salesforceCampaignId,
				$connector->getPiConnector()->id,
				true,
				true
			);

		if(!$salesforceCampaign) {
			// This should not fail. Verified in validation function.
			throw new RuntimeException('Failed to find CRM Campaign with CRM_FID: ' . $salesforceCampaignId);
		}

		// Align the Campaign
		$campaignAlignmentManager = new CampaignConnectManager($accountId);

		$campaign = $campaignAlignmentManager->alignCampaign(
			$recordActionContext->getAccessContext()->getUserId(),
			$connector,
			$pardotCampaign,
			$salesforceCampaign
		);

		if(!$campaign) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNKNOWN,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// Return the Representation
		return $this->loadCampaignRepresentationById(
			$recordActionContext->getVersion(),
			$accountId,
			$campaignId,
			$recordActionContext->getAccessContext()
		);
	}

	/**
	 * Loades the respective Campaign Representation
	 *
	 * @param int $version
	 * @param int $accountId
	 * @param int $campaignId
	 * @param AccessContext $accessContext
	 * @return CampaignRepresentation
	 */
	private function loadCampaignRepresentationById(
		int $version,
		int $accountId,
		int $campaignId,
		AccessContext $accessContext
	): CampaignRepresentation
	{
		$objectDefinitionCatalog = sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');

		$campaignObjectDefinition = $objectDefinitionCatalog->findObjectDefinitionByObjectType(
			$version,
			$accountId,
			'Campaign');

		$query = SingleResultQuery::from($accountId, $campaignObjectDefinition)
			->addSelections(
				$campaignObjectDefinition->getFieldByName('id'),
				$campaignObjectDefinition->getFieldByName('salesforceId'),
			)
			->addWhereEquals('id', $campaignId);

		$queryContext = new QueryContext($accountId, $version, $accessContext);
		$objectQueryManager = sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');

		$result = $objectQueryManager->queryOne($queryContext, $query);
		$representation = $result->getRepresentation();

		if (is_null($representation)) {
			throw new RuntimeException('Failed to find Campaign with ID: ' . $campaignId);
		}
		if (!($representation instanceof CampaignRepresentation)) {
			throw new RuntimeException('Query failed to return a CampaignRepresentation as requested. actual: ' . get_class($representation));
		}

		return $representation;
	}

	/**
	 * @param piAccount $account
	 * @return CRMManager
	 */
	protected function getCRMManager(piAccount $account): CRMManager
	{
		return new CRMManager($account);
	}

	/**
	 * @return piCampaignTable
	 */
	public function getPiCampaignTable(): piCampaignTable
	{
		return piCampaignTable::getInstance();
	}

	/**
	 * @return piCrmCampaignTable
	 */
	public function getCrmCampaignTable(): piCrmCampaignTable
	{
		return piCrmCampaignTable::getInstance();
	}

	/**
	 * @return piAccountTable
	 */
	public function getPiAccountTable(): piAccountTable
	{
		return piAccountTable::getInstance();
	}

}

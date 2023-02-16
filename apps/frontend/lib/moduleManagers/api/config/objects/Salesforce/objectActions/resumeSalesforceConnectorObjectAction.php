<?php

namespace Api\Config\Objects\Salesforce\ObjectActions;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\SalesforceRepresentation;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\ObjectActions\ObjectActionContext;
use Api\Config\Objects\Salesforce\Gen\ObjectActions\AbstractSalesforceResumeAction;
use SalesforceSyncPauseManager;
use SalesforceTrustedConnectionManager;
use piConnectorTable;
use sfContext;
use Api\Config\Objects\Salesforce\SalesforceHelper;
use ApiErrorLibrary;
use RESTClient;

class resumeSalesforceConnectorObjectAction extends AbstractSalesforceResumeAction
{
	protected ObjectDefinitionCatalog $objectDefinitionCatalog;

	/**
	 * @param ObjectDefinitionCatalog|null $objectDefinitionCatalog
	 * @throws \Exception
	 */
	public function __construct(
		?ObjectDefinitionCatalog $objectDefinitionCatalog = null
	)
	{
		$this->objectDefinitionCatalog = $objectDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @return SalesforceRepresentation|null
	 * @throws \Exception
	 */
	public function executeActionWithArgs(ObjectActionContext $objectActionContext): ?SalesforceRepresentation
	{
		$accountId = $objectActionContext->getAccountId();
		$sfSyncPauseMgr = SalesforceSyncPauseManager::getInstance($accountId);
		$connector = piConnectorTable::getInstance()->getSalesforceConnector($accountId);

		if (!$connector) {
			// no connector to pause
			throw new ApiException(ApiErrorLibrary::API_ERROR_NO_SALESFORCE_CONNECTOR,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$isV2 = SalesforceTrustedConnectionManager::isTrustedConnection($connector);
		$isPaused = $sfSyncPauseMgr->isProspectAndCustomObjectSyncPaused();
		$salesforceHelper = new SalesforceHelper($accountId, $objectActionContext->getApiRequest()->getUserId());

		if ($isV2 && $isPaused) {
			$salesforceHelper->createSyncPauseConnectorObjectAudit($connector, 0);
			$sfSyncPauseMgr->unpauseProspectAndCustomObjectSync();
		} else if (!$isV2) {
			// connector isn't v2
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_SALESFORCE_CONNECTOR_VERSION,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		} else if (!$isPaused) {
			// connector is already unpaused
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_SALESFORCE_CONNECTOR_STATE,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		return $salesforceHelper->getSalesforceRepresentation($objectActionContext, $this->objectDefinitionCatalog);
	}
}

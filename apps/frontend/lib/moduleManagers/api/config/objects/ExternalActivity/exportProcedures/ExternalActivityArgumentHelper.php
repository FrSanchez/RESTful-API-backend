<?php
namespace Api\Config\Objects\ExternalActivity\ExportProcedures;

use Api\Exceptions\ApiException;
use Pardot\ExternalActivity\ExternalActivityVersionManager;
use ApiErrorLibrary;
use GraphiteClient;
use RESTClient;

class ExternalActivityArgumentHelper
{
	/**
	 * Determines if the external activity data store has been provisioned.
	 * @param int $accountId
	 * @throws ApiException
	 */
	public static function isExternalActivityProvisioned(int $accountId)
	{
		$versionManager = new ExternalActivityVersionManager();
		$dataStoreEnabled = $versionManager->isDataStoreEnabled($accountId);

		// Validate that the data store is enabled for the account
		if (!$dataStoreEnabled) {
			GraphiteClient::increment("externalActivity.account.{accountId}.error.datastoreNotProvisioned");
			throw new ApiException(
				ApiErrorLibrary::API_ENDPOINT_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}
	}
}

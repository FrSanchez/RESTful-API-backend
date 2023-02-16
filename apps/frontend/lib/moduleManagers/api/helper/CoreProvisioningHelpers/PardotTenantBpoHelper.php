<?php
/**
 * Created by PhpStorm.
 * User: adam.bonk
 * Date: 2020-10-13
 * Time: 10:55
 */

class PardotTenantBpoHelper
{

	public const STATUS_DELETED = "Deleted";


	/** Runs the given function 'attempts' number of times with an exponential backoff. Function is wrapped in a try/catch,
	 * any exceptions are logged and treated as a failure.
	 *
	 * @param int $attempts Number of attempts to make
	 * @param string $label Label for the function, used for logging
	 * @param callable $function Function to call, must return true if success, false if failed; cannot take params, but can use closures
	 * @param bool $sleep Used for testing, should be true in production to ensure we use exponential backoff
	 * @return bool True if succeeded, false otherwise
	 */
	public function retriesWithBackoff($attempts, $label, $function, $sleep = true) {
		for ($i = 1; $i <= $attempts; $i++) {
			try {
				$function();

				PardotLogger::getInstance()->info("$label succeeded, exiting");
				return true;
			} catch (Exception $e) {
				PardotLogger::getInstance()->warn("$label did not succeed, exception {$e->getMessage()}");
			}

			if ($i < $attempts) {
				// Don't sleep the last time, because why?
				$sleeptime = ($i ** 2) * 10;
				PardotLogger::getInstance()->warn("$label did not succeed, sleeping for $sleeptime seconds to try again");
				GraphiteClient::increment("sfdc.backfill.retries.iterator$i");

				// $this->sleep is used to stop sleep in tests
				if ($sleep) {
					sleep($sleeptime);
				}
			} else {
				PardotLogger::getInstance()->error("$label did not succeed the final time, exiting without sleeping");
			}
		}
		return false;
	}

	/**
	 * This needs to be broken out into a seperate class to be reused by backfill and this job
	 * Gets the integration user connection for a given account and orgId
	 *
	 * @param $accountId
	 * @param null|string $orgId Override the orgId for the connection
	 * @param string $ver version of the salesforce rest api we're using
	 * @return SalesforceRestApiWithJwtHelperWithoutConnector
	 */
	public function getIntegrationUserConnection($accountId, $orgId, $ver = SalesforceRestApiAbstract::V_50)
	{

		$sfdcClient[$orgId.$ver] = new SalesforceRestApiWithJwtHelperWithoutConnector(
			$accountId,
			$orgId,
			null,
			null,
			null,
			$ver);

		return $sfdcClient[$orgId.$ver];
	}

	/**
	 * updates the pardot tenant status on core.
	 * @param $accountId
	 * @param $status
	 * @param $id
	 * @param $orgId
	 * @param $sleep
	 * @param $retries
	 * @return bool
	 */
	public function updatePardotTenantStatusOnCore($accountId, $status, $id, $orgId, $sleep = true, $retries = 1) {
		$client = $this->getIntegrationUserConnection($accountId, $orgId, SalesforceRestApiAbstract::V_49);

		$result = null;
		$sfdcUpdateFunction = function() use (&$client, &$result, &$status, &$id) {
			$json = [
				'CreationStatus' => $status
			];
			$result = $client->patch("tooling/sobjects/PardotTenant/$id", [
				'json' => $json
			]);
			return $result;
		};

		$resultOfAll = $this->retriesWithBackoff($retries, "Update request to Salesforce for account $accountId, status $status, for bpo ID $id on org $orgId", $sfdcUpdateFunction, $sleep);
		if (!$resultOfAll) {
			PardotLogger::getInstance()->warn("Unable to update pardot record $id on core org $orgId for account $accountId with status {$status}");
			return false;
		}
		return true;
	}
}
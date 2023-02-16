<?php

class OrgLevelCrossBusinessUnitScopeC2cHelper implements PardotC2cCustomClaimsHelperInterface
{
	// This is a system-reserved service name used to identify C2C-auth enabled businessUnit API requests in JWT cc.iss claim
	const BUSINESS_UNIT_CONTEXT_SERVICE_NAME = "BusinessUnitContext";
	const BUSINESS_UNIT_CONTEXT_METRIC_PREFIX = "api.request.c2c.business_unit_context.";

	use Singleton;

	/**
	 * @param PardotC2cAuthorizationHeader $apiAuthHeader
	 * @return bool true if the C2C custom claims are valid.  Otherwise, false.
	 */
	public function validateC2cCustomClaims(PardotC2cAuthorizationHeader $apiAuthHeader) : bool
	{
		$serviceName = $apiAuthHeader->getServiceName() ?? "n/a";
		$isBuContext = $this->isBusinessUnitContextServiceType($serviceName);
		$isValid = ($isBuContext && $apiAuthHeader->getUsername());
		if (!$isValid) {
			$usernameIsSet = $apiAuthHeader->getUsername() ? "REDACTED username" : "n/a";
			PardotLogger::getInstance()->error("Invalid C2C Auth JWT claims for " .
				self::BUSINESS_UNIT_CONTEXT_SERVICE_NAME . ": cc.iss={$serviceName}, cc.sub={$usernameIsSet}"
			);
			$this->incrementBusinessUnitContextMetric("failure.validation");
		}
		return $isValid;
	}

	/**
	 * Gets a Pardot user that is CRM-linked to the specified crmUsername across any of the Pardot business units with
	 * a verified connection to the same Salesforce org associated with the specified accountId favoring selecting user
	 * that exists in the specified accountId before attempting to select user in other associated business units.
	 * @param int $accountId
	 * @param string $crmUsername
	 * @param bool $forceSelectAdminUser
	 * @param string|null $errorMessage
	 * @param int|null $errorCode
	 * @return piUser|null
	 * @throws Doctrine_Query_Exception
	 */
	public function getC2cApiUser(int $accountId, string $crmUsername, bool &$forceSelectAdminUser, ?string &$errorMessage, ?int &$errorCode): ?piUser
	{
		$forceSelectAdminUser = false;
		$apiUser = $this->getPardotUserFromCrmUsername($accountId, $crmUsername);
		if ($apiUser) {
			$this->incrementBusinessUnitContextMetric("success.ok");
		} else {
			// Get org id for the account
			$piAccount = piAccountTable::getInstance()->retrieveById($accountId); // backed by cache
			$orgFid = $piAccount->sfdc_fid;
			if (empty($orgFid)) {
				$errorMessage = "missing org fid for account " . $accountId;
				$this->incrementBusinessUnitContextMetric("failure.missing_org_fid");
				return null;
			}
			// Get other accounts connected to same org id
			$sfdc_org = piSfdcOrgTable::getInstance()->fetchOneByFid($orgFid); // backed by cache
			if ($sfdc_org) {
				$eligibleAccountIds = piGlobalAccountTable::getInstance()->getAccountIdsVerifiedAgainstSfdc($sfdc_org->id, true);
			} else {
				$errorMessage = "invalid org fid " . $orgFid;
				$this->incrementBusinessUnitContextMetric("failure.invalid_org_fid");
				return null;
			}
			if ($eligibleAccountIds) {
				// remove the original account id from the set because we already know it does not have this CRM user
				$eligibleAccountIds = array_flip($eligibleAccountIds);
				unset($eligibleAccountIds[$accountId]);
				$eligibleAccountIds = array_keys($eligibleAccountIds);
			}
			if (empty($eligibleAccountIds)) {
				$errorMessage = "CRM username not assigned to single business unit associated with org $orgFid. No other verified accounts found.";
				$errorCode = ApiErrorLibrary::API_ERROR_INVALID_USER_ID;
				$this->incrementBusinessUnitContextMetric("success.user_not_assigned");
				return null;
			}
			// Get CRM user fid for CRM username in this set of connected accounts
			$piGlobalUser = piGlobalUserTable::getInstance()->findGloballyByCrmUsernameAndAccountIds($crmUsername, $eligibleAccountIds);
			if ($piGlobalUser) {
				ShardManager::getInstance()->setTenantContext($piGlobalUser->account_id);
				$apiUser = $this->getPardotUserFromCrmUsername($piGlobalUser->account_id, $crmUsername);
				$this->incrementBusinessUnitContextMetric("success.mbus"); // found user in connected BU of a multi-BU org
			} else {
				// return empty response because CRM user is not associated with any BUs
				$this->incrementBusinessUnitContextMetric("success.mbus_user_not_assigned");
				$errorCode = ApiErrorLibrary::API_ERROR_INVALID_USER_ID;
				$errorMessage = "CRM username not assigned to any active business units associated with org $orgFid";
				$apiUser = null;
			}
		}
		return $apiUser;
	}

	/**
	 * @param $accountId
	 * @param $username
	 * @return piUser|null|bool
	 */
	protected function getPardotUserFromCrmUsername($accountId, $username)
	{
		return piUserTable::getInstance()->retrieveOneByCRMUsername($accountId, $username);
	}

	/**
	 * Check if the service name identifies the request as a C2C-auth enabled Business Unit Context API request
	 * @param $serviceName
	 * @return bool
	 */
	protected function isBusinessUnitContextServiceType($serviceName) : bool
	{
		return strcasecmp($serviceName, self::BUSINESS_UNIT_CONTEXT_SERVICE_NAME) === 0;
	}

	protected function incrementBusinessUnitContextMetric(string $metricName)
	{
		GraphiteClient::increment(self::BUSINESS_UNIT_CONTEXT_METRIC_PREFIX . $metricName);
	}
}

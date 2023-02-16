<?php

/**
 * PardotC2cCustomClaimsHelperInterface
 *
 * This is a common interface for applying custom validation logic and API user selection rules based on C2C custom
 * claims in the PardotC2cAuthorizationHeader such as cc.iss (service name) and cc.sub (username).  API endpoints that
 * require specialized handling of C2C custom claims shall pass an object instance of a concrete class that implements
 * this interface as input to the apiActions::initializeApiRequest() method.  API endpoints that do not require special
 * handling of C2C custom claims shall not specify a $c2cCustomClaimsHelper when calling initializeApiRequest().  The
 * default behavior when no $c2cCustomClaimsHelper is specified is that there is no validation of the cc.iss (service name)
 * custom claim and the API user is selected based on cc.sub (CRM username) claim if specified or, if the cc.sub claim
 * is not specified, a non-archived admin user in the account is selected instead.
 */
interface PardotC2cCustomClaimsHelperInterface {

	/**
	 * Validate the C2C custom claims in the PardotC2cAuthorizationHeader such as cc.iss (service name) and cc.sub (username)
	 * @param PardotC2cAuthorizationHeader $apiAuthHeader
	 * @return bool true if the C2C custom claims are valid.  Otherwise, false.
	 */
	public function validateC2cCustomClaims(PardotC2cAuthorizationHeader $apiAuthHeader) : bool;

	/**
	 * Gets Pardot user whose access and abilities shall be used for authorizing and processing the API request based on
	 * custom claims in the PardotC2cAuthorizationHeader.
	 * @param int $accountId identifies the Pardot account that the API request was originally authenticated against
	 * @param string $crmUsername identifies Salesforce user from the custom cc.sub claim of the C2C JWT that initiated
	 * the action or query in Salesforce resulting in the Pardot API call for which a Pardot API user is being selected
	 * @param bool $forceSelectAdminUser output param used to indicate whether the system shall attempt to select an admin
	 * user in the account for servicing the API request in cases where no api user is returned by this helper function
	 * @param string|null $errorMessage output param that provides human-readable error message for logging purposes on failure
	 * @param int|null $errorCode output param that identifies the error code to be reported to the API client on failure
	 * @return piUser|null non-null API user on success.  null on failure or if custom claims requires that the
	 * system select an admin user in the account as indicated by the $forceSelectAdminUser output param.
	 * @throws Doctrine_Query_Exception
	 */
	public function getC2cApiUser(
		int $accountId,
		string $crmUsername,
		bool &$forceSelectAdminUser,
		?string &$errorMessage,
		?int &$errorCode
	) : ?piUser;
}

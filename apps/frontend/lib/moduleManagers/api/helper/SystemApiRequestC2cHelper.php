<?php

class SystemApiRequestC2cHelper implements PardotC2cCustomClaimsHelperInterface
{
	// This prefix in the service name used to indicate that the API is triggered by the SF system user such as
	// autoproc or b2bma integration user that don't have corresponding user in Pardot
	const SYSTEM_SERVICE_NAME_PREFIX = "System";

	use Singleton;

	/**
	 * @param PardotC2cAuthorizationHeader $apiAuthHeader
	 * @return bool true if the C2C custom claims are valid.  Otherwise, false.
	 */
	public function validateC2cCustomClaims(PardotC2cAuthorizationHeader $apiAuthHeader) : bool
	{
		return $this->isSystemServiceType($apiAuthHeader->getServiceName());
	}

	/**
	 * Check if the service name has "system" or "System" prefix which indicates that the API is initiated by a system
	 * user (such as autoproc or b2bmaIntegration user)
	 * @param $serviceName
	 * @return bool
	 */
	protected function isSystemServiceType($serviceName) : bool
	{
		return (strncasecmp($serviceName, self::SYSTEM_SERVICE_NAME_PREFIX, strlen(self::SYSTEM_SERVICE_NAME_PREFIX)) === 0);
	}

	public function getC2cApiUser(int $accountId, string $crmUsername, bool &$forceSelectAdminUser, ?string &$errorMessage, ?int &$errorCode): ?piUser
	{
		$forceSelectAdminUser = true;
		return null;
	}
}

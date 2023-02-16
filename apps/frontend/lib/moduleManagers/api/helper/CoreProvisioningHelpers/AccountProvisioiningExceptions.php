<?php
class AccountProvisioningException extends Exception
{
	/** @var mixed array */
	protected $accountInfo = null;
	protected $statusCode;

	public function __construct(string $msg, $accountInfo = null, $statusCode = ProvisioningStatusCodes::UNKNOWN_ERROR)
	{
		//unknown error is the default
		$this->statusCode = $statusCode;
		if ($accountInfo) {
			$this->accountInfo = $accountInfo;
		}
		parent::__construct($msg);
	}

	final public function getAccountInfo()
	{
		return $this->accountInfo;
	}

	/**
	 * @return string
	 */
	public function getStatusCode()
	{
		return $this->statusCode;
	}
}

class SandboxTenantLimitExceededException extends AccountProvisioningException {

	public function __construct(string $msg, $accountInfo = null)
	{
		parent::__construct($msg, $accountInfo, ProvisioningStatusCodes::INSUFFICIENT_TENANT_LICENSES);
	}
}

class UsernameCollisionException extends AccountProvisioningException {

	public function __construct(string $msg, $accountInfo = null)
	{
		parent::__construct($msg, $accountInfo, ProvisioningStatusCodes::USERNAME_COLLISION);
	}
}

class InvalidRequestException extends AccountProvisioningException {

	public function __construct(string $msg, $accountInfo = null)
	{
		parent::__construct($msg, $accountInfo, ProvisioningStatusCodes::INVALID_REQUEST);
	}
}

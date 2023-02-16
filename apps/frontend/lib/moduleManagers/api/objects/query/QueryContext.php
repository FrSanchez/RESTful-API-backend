<?php
namespace Api\Objects\Query;

use Api\Framework\ApiRequest;
use Api\Objects\Access\AccessContext;

/**
 * Information about the system state when executing a query.
 *
 * Class QueryContext
 * @package Api\Objects\Query
 */
class QueryContext
{
	/** @var int $accountId */
	private $accountId;

	/** @var int $version */
	private $version;
	private $accessContext;

	public function __construct(int $accountId, int $version, AccessContext $accessContext)
	{
		$this->accountId = $accountId;
		$this->version = $version;
		$this->accessContext = $accessContext;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Gets the access context used for executing a query.
	 * @return mixed
	 */
	public function getAccessContext()
	{
		return $this->accessContext;
	}

	public static function createFromApiRequest(ApiRequest $apiRequest): QueryContext
	{
		return new QueryContext(
			$apiRequest->getAccountId(),
			$apiRequest->getVersion(),
			$apiRequest->getAccessContext()
		);
	}
}

<?php
namespace Api\Objects\ObjectActions;

use Api\Framework\ApiRequest;
use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinition;
use apiActions;

/**
 * Context for the execution of an object action within the API.
 */
class ObjectActionContext
{
	private int $accountId;
	private int $version;
	private AccessContext  $accessContext;
	private ObjectActionDefinition $objectActionDefinition;
	private ObjectDefinition $objectDefinition;
	private apiActions $apiActions;
	private ApiRequest $apiRequest;

	public function __construct(
		int $accountId,
		int $version,
		AccessContext $accessContext,
		ObjectActionDefinition $objectActionDefinition,
		ObjectDefinition $objectDefinition,
		apiActions $apiActions,
		ApiRequest $apiRequest
	) {
		$this->accountId = $accountId;
		$this->version = $version;
		$this->accessContext = $accessContext;
		$this->objectActionDefinition = $objectActionDefinition;
		$this->objectDefinition = $objectDefinition;
		$this->apiActions = $apiActions;
		$this->apiRequest = $apiRequest;
	}

	/**
	 * Gets the ID of the account in which the object action should be executed.
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * Gets the version number of the request in which the object action is being executed.
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Gets the access context of the request executing the object action.
	 * @return AccessContext
	 */
	public function getAccessContext(): AccessContext
	{
		return $this->accessContext;
	}

	/**
	 * Gets the definition of the object associated to the object action.
	 * @return ObjectDefinition
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * Gets the definition of the object action associated to the object action being executed.
	 * @return ObjectActionDefinition
	 */
	public function getObjectActionDefinition(): ObjectActionDefinition
	{
		return $this->objectActionDefinition;
	}

	/**
	 * Gets the api action initialized for the request being executed.
	 * @return apiActions
	 */
	public function getApiActions(): apiActions
	{
		return $this->apiActions;
	}

	/**
	 * Gets the api request initialized for the request being executed.
	 * @return ApiRequest
	 */
	public function getApiRequest(): ApiRequest
	{
		return $this->apiRequest;
	}
}

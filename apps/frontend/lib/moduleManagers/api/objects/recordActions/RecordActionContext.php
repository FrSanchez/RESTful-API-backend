<?php
namespace Api\Objects\RecordActions;

use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinition;

/**
 * The context in which a record action is executed.
 *
 * Class RecordActionContext
 * @package Api\Objects\RecordActions
 */
class RecordActionContext
{
	private int $accountId;
	private bool $isInternalRequest;
	private int $version;
	private int $recordId;
	private AccessContext  $accessContext;
	private RecordActionDefinition $recordActionDefinition;
	private ObjectDefinition $objectDefinition;

	public function __construct(
		int $accountId,
		int $version,
		int $recordId,
		AccessContext $accessContext,
		RecordActionDefinition $recordActionDefinition,
		ObjectDefinition $objectDefinition,
		bool $isInternalRequest = false
	) {
		$this->accountId = $accountId;
		$this->version = $version;
		$this->recordId = $recordId;
		$this->accessContext = $accessContext;
		$this->recordActionDefinition = $recordActionDefinition;
		$this->objectDefinition = $objectDefinition;
		$this->isInternalRequest = $isInternalRequest;
	}

	/**
	 * Gets the ID of the account in which the record action should be executed.
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * Gets the version number of the request in which the record action is being executed.
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Gets the ID of the record associated to the record action.
	 * @return int
	 */
	public function getRecordId(): int
	{
		return $this->recordId;
	}

	/**
	 * Gets the access context of the request executing the record action.
	 * @return AccessContext
	 */
	public function getAccessContext(): AccessContext
	{
		return $this->accessContext;
	}

	/**
	 * Gets the definition of the object associated to the record action.
	 * @return ObjectDefinition
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * Gets the definition of the record action associated to the record action.
	 * @return RecordActionDefinition
	 */
	public function getRecordActionDefinition(): RecordActionDefinition
	{
		return $this->recordActionDefinition;
	}

	/**
	 * Gets if the request is internal/external
	 * @return bool
	 */
	public function isInternalRequest(): bool
	{
		return $this->isInternalRequest;
	}
}

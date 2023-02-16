<?php
namespace Api\Objects\Query;

use Api\Exceptions\ApiException;
use Api\Framework\ApiWarnings;
use Api\Objects\ObjectDefinition;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use DateTime;
use RESTClient;

/**
 * The "lightweight" record use for checking preconditions on a record.
 *
 * Class PreconditionRecord
 * @package Api\Framework
 */
class PreconditionRecord
{
	private ObjectDefinition $objectDefinition;
	private array $data;

	public function __construct(ObjectDefinition $objectDefinition, array $data)
	{
		$this->objectDefinition = $objectDefinition;
		$this->data = $data;
	}

	public function isDeleted(): bool
	{
		return $this->objectDefinition->getStandardFieldByName(SystemFieldNames::IS_DELETED) &&
			isset($this->data[SystemFieldNames::IS_DELETED]) &&
			$this->data[SystemFieldNames::IS_DELETED] === true;
	}

	/**
	 * Throws a NOT FOUND exception if the record has been deleted (aka is_archived).
	 * @throws ApiException
	 */
	public function failWithNotFoundIfDeleted(): void
	{
		if ($this->isDeleted()) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND,
				null,
				[
					ApiWarnings::HTTP_HEADER => ApiWarnings::createWarningMessageForHttpHeader(
						ApiWarnings::RECORD_IN_RECYCLE_BIN
					)
				]
			);
		}
	}

	/**
	 * @param DateTime $ifModifiedSince
	 * @return bool
	 */
	public function isModifiedSince(DateTime $ifModifiedSince): bool
	{
		if (!isset($this->data[SystemFieldNames::UPDATED_AT]) ||
			!($this->data[SystemFieldNames::UPDATED_AT] instanceof DateTime)) {
			// Unable to determine updated_at so return that it has been modified
			return true;
		}
		$updatedAt = $this->data[SystemFieldNames::UPDATED_AT];

		return $updatedAt > $ifModifiedSince;
	}
}

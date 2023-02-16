<?php

namespace Api\Objects\Query;

use Api\Objects\RecordIdCollection;
use Api\Representations\Representation;

/**
 * Response returned when executing a query which returns a single result.
 *
 * Class SingleResultQueryResult
 * @package Api\Objects\Query
 */
class SingleResultQueryResult
{
	private $representation;
	private $additionalFields;
	private $redactedRecordIds;

	public function __construct(
		?Representation $representation,
		array $additionalFields,
		RecordIdCollection $redactedRecordIds
	) {
		$this->representation = $representation;
		$this->additionalFields = $additionalFields;
		$this->redactedRecordIds = $redactedRecordIds;
	}

	public function getRepresentation(): ?Representation
	{
		return $this->representation;
	}

	/**
	 * @param string $fieldName
	 * @return mixed|null
	 */
	public function getAdditionalFieldByName(string $fieldName)
	{
		return $this->additionalFields[$fieldName] ?? null;
	}

	/**
	 * When executing a query, related records can be redacted due to the user not having access the record.
	 * @return mixed
	 */
	public function getRedactedRecordIds(): RecordIdCollection
	{
		return clone $this->redactedRecordIds;
	}
}

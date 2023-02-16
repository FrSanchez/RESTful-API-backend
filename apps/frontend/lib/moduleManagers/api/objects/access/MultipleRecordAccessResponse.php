<?php
namespace Api\Objects\Access;

use Api\Objects\ObjectDefinition;
use Api\Objects\RecordIdCollection;

/**
 * Response for when calculating record access from the {@see ObjectAccessManager::canUserAccessRecords()}.
 *
 * Class MultipleRecordAccessResponse
 * @package Api\Objects\Access
 */
class MultipleRecordAccessResponse
{
	private $accessibleRecords;

	public function __construct(RecordIdCollection $accessibleRecordIds)
	{
		// making a clone to protect from modification outside of this instance.
		$this->accessibleRecords = clone $accessibleRecordIds;
	}

	/**
	 * Determines if the record has access or not. Care should be taken in that only records specified in
	 * {@see ObjectAccessManager::canUserAccessRecords()} will be returned in the collection. Specifying a record
	 * as an argument in this method that wasn't specified when calculating access will result in the record being
	 * inaccessible (returns false).
	 *
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool True if the user can access the record otherwise false is returned.
	 */
	public function canUserAccessRecord(ObjectDefinition $objectDefinition, int $recordId): bool
	{
		return $this->accessibleRecords->containsRecordId($objectDefinition, $recordId);
	}
}

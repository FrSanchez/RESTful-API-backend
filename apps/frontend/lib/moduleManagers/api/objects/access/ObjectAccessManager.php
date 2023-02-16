<?php
namespace Api\Objects\Access;

use Api\Objects\ObjectDefinition;
use Api\Objects\RecordIdCollection;

interface ObjectAccessManager
{
	/**
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @return bool
	 * @throws AccessException
	 */
	public function canUserAccessObject(
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition
	): bool;

	/**
	 * Determines if a single record can be accessed within the given Access Context. The record is not checked for existence
	 * therefore a record that does not exist has indeterminate behavior.
	 *
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @return bool
	 * @throws AccessException
	 */
	public function canUserAccessRecord(
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		int $recordId
	): bool;

	/**
	 * Determines if the records given can be accessed within the given Access Context. Each record is not checked for existence
	 * therefore a record that does not exist has indeterminate behavior.
	 *
	 * @param AccessContext $accessContext
	 * @param RecordIdCollection $recordIds
	 * @return MultipleRecordAccessResponse
	 */
	public function canUserAccessRecords(
		AccessContext $accessContext,
		RecordIdCollection $recordIds
	): MultipleRecordAccessResponse;
}

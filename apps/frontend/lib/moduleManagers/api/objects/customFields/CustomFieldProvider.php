<?php


namespace Api\Objects;

/**
 * Represents a provider for an object's custom field information and data. Implementing classes are in charge of
 * loading and describing what functionality is available for the custom fields so that the field information can be
 * used in the API to verify input from the user. When a record is loaded, the Custom Field Provider is in charge of
 * loading custom data for return to the user.
 *
 * Interface CustomFieldProvider
 * @package Api\Objects
 */
interface CustomFieldProvider
{
	/**
	 * Retrieves StaticFieldDefinitions for additional fields to be contributed to the Object Definition. This method
	 * can be invoked multiple times within a single request and it's up to the implementing class to provide an
	 * efficient manner of retrieval (eg. caching of field information).
	 * @param int $accountId
	 * @param int $version
	 * @return StaticFieldDefinition[]
	 */
	public function getAdditionalFields(int $accountId, int $version):array;

	/**
	 * Retrieve data (values) for additional fields
	 *
	 * @param string[] $fieldNames
	 * @param int $accountId
	 * @param int[] $ids Pardot object record IDs
	 * @param int $version Pardot API version
	 * @return array Returns an array:
	 * 		- the array's key should be the Pardot ID for the object record and the value is an inner array.
	 *  	- the inner array should use each field name as the key and value of the field as the member's value.
	 * 		NOTE: the returned array should contain a result for all object IDs and all fields requested. If the value
	 * 		of a field is unknown, then a default value or null should be returned.
	 */
	public function getAdditionalFieldData(array $fieldNames, int $accountId, int $version, array $ids):array;
}

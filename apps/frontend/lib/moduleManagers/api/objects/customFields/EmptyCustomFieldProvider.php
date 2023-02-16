<?php

namespace Api\Objects;

use Api\DataTypes\DataTypeCatalog;
use \RuntimeException;

/**
 * Default Custom Field Provider utilized when a provider isn't specified otherwise for a given object
 *
 * Class EmptyCustomFieldProvider
 * @package Api\Objects
 */
class EmptyCustomFieldProvider implements CustomFieldProvider
{
	use \Singleton;

	/**
	 * Retrieve an empty list of custom fields (meta-data)
	 *
	 * @param int $accountId
	 * @param int $version
	 * @return array
	 */
	public function getAdditionalFields(int $accountId, int $version):array
	{
		return [];
	}

	/**
	 * Throw an error on attempt to retrieve custom field data
	 *
	 * @param array $fieldNames
	 * @param int $accountId
	 * @param array $ids
	 * @return array
	 * @throws CustomFieldNotFoundException
	 */
	public function getAdditionalFieldData(array $fieldNames, int $accountId, int $version, array $ids):array
	{
		return [];
	}
}

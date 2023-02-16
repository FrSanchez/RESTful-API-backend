<?php
namespace Api\Config\Objects\Prospect;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\DataType;
use Api\DataTypes\DataTypeCatalog;
use Api\DataTypes\OneOfPrimitiveOrArrayOfPrimitiveDataType;
use Api\Objects\CustomFieldProvider;
use Api\Objects\StaticFieldDefinition;
use Api\Objects\StaticFieldDefinitionBuilder;
use Exception;
use FormFieldPeer;
use RuntimeException;
use piProspectFieldCustom;
use piProspectFieldCustomTable;
use piProspectFieldCustomStorage;
use piProspectFieldCustomStorageTable;
use Doctrine_Query;
use Doctrine_Query_Exception;
use ApiFrameworkConstants;

/**
 * Class ProspectCustomFieldProvider
 * @package Api\Objects
 */
class ProspectCustomFieldProvider implements CustomFieldProvider
{
	/**
	 * Retrieve the list of custom fields (meta-data)
	 *
	 * @param int $accountId
	 * @param int $version
	 * @return StaticFieldDefinition[]
	 * @throws Doctrine_Query_Exception
	 */
	public function getAdditionalFields(int $accountId, int $version):array
	{
		$fieldDefs = [];
		$dbCustomFields = piProspectFieldCustomTable::getInstance()
			->findByAccountIdWithNoIndexAndNoOrdering($accountId, false, true);

		foreach ($dbCustomFields as $field) {
			/** @var piProspectFieldCustom $field */
			try {
				$dataType = DataTypeCatalog::getDataTypeByDatabaseEnum($field->type);
			} catch (RuntimeException $exception) {
				throw new RuntimeException("Unrecognized value '" . $field->type . "' " .
					" specified for 'type' property in custom field " . $field->field_id . ". " .
					"The value must be the enumeration of a recognized data-type.", 0, $exception);
			}

			// For versions 3/4, we want the name to be the same as field_id. For version 5, we add "__c" to disambiguate from standard fields.
			if ($version <= 4) {
				$name = $field->field_id;
			} else {
				$name = $field->field_id . ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX;
			}

			// For version 3/4, fields with isRecordMultipleResponses only returned the most recent however in >= v5,
			// any number of values can be returned so an array is needed.
			if ($version >= 5 && $field->is_record_multiple_responses &&
				$field->type != FormFieldPeer::TYPE_CHECKBOX && $field->type != FormFieldPeer::TYPE_MULTI_SELECT) {
				$dataType = new OneOfPrimitiveOrArrayOfPrimitiveDataType($dataType);
			}

			$fieldDefs[] = StaticFieldDefinitionBuilder::create()
				->withName($name, $field->field_id)
				->withDataType($dataType)
				->withCustom(true)
				->withBulkDataProcessorClass("\Api\Config\BulkDataProcessors\CustomFieldBulkDataProcessor")
				->build();
		}

		return $fieldDefs;
	}

	/**
	 * Retrieve values for Prospect custom field(s)
	 *
	 * Requesting fields that no longer exist will throw an exception
	 * Output preserves order of custom fields input
	 * For Number and Date fields with multiple custom field values for a Prospect, value is most recently updated_at
	 * For Text fields, Prospect custom field values are concatenated together with semicolon
	 * Prospects without custom field value set will return default of null for each value
	 *
	 *
	 * @param array $fieldNames
	 * @param int $accountId
	 * @param int $version
	 * @param array $ids
	 * @return array
	 * @throws Exception
	 */
	public function getAdditionalFieldData(array $fieldNames, int $accountId, int $version, array $ids):array
	{
		$fieldIds = ($version >= 5) ? array_map(fn($fieldName) => substr($fieldName, 0, -3), $fieldNames) : $fieldNames;
		$customFields = piProspectFieldCustomTable::getInstance()->findByFieldIds($accountId, $fieldIds, false, 'pfc.field_id', true)->getData();

		// Create a mapping from ID to field_id for later translation
		$fieldMap = [];
		$dataTypeMap = [];
		foreach ($customFields as $field) {
			/** @var piProspectFieldCustom $field */
			if (!in_array($field->field_id, $fieldIds)) { // Will this ever be true? Doesn't seem so.
				continue;
			}

			$fieldMap[$field->id] = $field;

			try {
				$dataType = DataTypeCatalog::getDataTypeByDatabaseEnum($field->type);
			} catch (RuntimeException $exception) {
				throw new RuntimeException("Unrecognized value '" . $field->type . "' " .
					" specified for 'type' property in custom field " . $field->field_id . ". " .
					"The value must be the enumeration of a recognized data-type.", 0, $exception);
			}
			$dataTypeMap[$field->id] = $dataType;
		}
		unset($customFields);

		// Create the default values array
		$defaults = [];
		foreach ($fieldNames as $fieldName) {
			$defaults[$fieldName] = null;
		}

		$results = [];
		foreach ($ids as $id) {
			$results[$id] = $defaults;
		}
		unset($defaults);

		// If no fields are passed in, return the default results
		if (empty($fieldMap)) {
			return $results;
		}

		$query = Doctrine_Query::create();
		$query->select('cs.prospect_field_custom_id, cs.prospect_id, cs.value, cs.updated_at')
			->from('piProspectFieldCustomStorage cs')
			->where('cs.account_id = ?', $accountId)
			->whereIn('cs.prospect_id', $ids)
			->whereIn('cs.prospect_field_custom_id', array_keys($fieldMap))
			->readReplicaSafe();
		$customFieldStorageValues = $query->executeAndFree();
		unset($query);

		// Keep track of most recent custom field value by updated_at for NUMBER and DATE types
		$customFieldMostRecentUpdatedAt = [];

		// Fill the results array with values from the custom_field_storage table
		foreach ($customFieldStorageValues as $storageValue) {
			/** @var piProspectFieldCustomStorage $storageValue */

			// Only add custom fields that were requested
			if (!array_key_exists($storageValue->prospect_field_custom_id, $fieldMap)) {
				continue;
			}

			/** @var piProspectFieldCustom $prospectFieldCustom */
			$prospectFieldCustom = $fieldMap[$storageValue->prospect_field_custom_id];
			$fieldName = $version >= 5 ? $prospectFieldCustom->field_id . ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX : $prospectFieldCustom->field_id;

			// The database may store values that are not valid for the type specified. For example, storing a string
			// value of "hello" in a number field. This happens when a user changes the data type of the custom field
			// after data has been saved.
			// In v3/v4, the data is always returned (regardless of if it's valid)
			// In v5, the value is redacted
			if ($version <= 4) {
				$value = $storageValue->value;
			} else {
				/** @var DataType $dataType */
				$dataType = $dataTypeMap[$storageValue->prospect_field_custom_id];
				if ($dataType instanceof ArrayDataType) {
					// Unwrap the item array since this is an item
					$dataType = $dataType->getItemDataType();
				}
				try {
					$dataType->convertDatabaseValueToServerValue($storageValue->value);
					$value = $storageValue->value;
				} catch (Exception $exception) {
					// Attempt a conversion and if it fails, set the value to null and continue
					$value = null;
				}
			}

			$thisProspectId = $storageValue->prospect_id;

			// Multi-value data is only type that can contain multiple values simultaneously
			if ($this->isMultiValueDataType($prospectFieldCustom->type)) {
				$results[$thisProspectId][$fieldName][] = $value;
				continue;
			}

			// For v5, isRecordMultipleResponses returns all values in an array.
			// For v3/v4, isRecordMultipleResponses returns only the most recent value
			if ($version >= 5 && $prospectFieldCustom->is_record_multiple_responses) {
				$results[$thisProspectId][$fieldName][] = $value;
				continue;
			}

			if (!array_key_exists($thisProspectId, $customFieldMostRecentUpdatedAt)) {
				$customFieldMostRecentUpdatedAt[$thisProspectId] = [];
			}
			$thisProspectCustomFieldInfo = &$customFieldMostRecentUpdatedAt[$thisProspectId];

			if (!array_key_exists($prospectFieldCustom->field_id, $thisProspectCustomFieldInfo)) {
				$thisProspectCustomFieldInfo[$prospectFieldCustom->field_id] = $storageValue->updated_at;
			}

			$previousCustomStorageUpdateAt = $thisProspectCustomFieldInfo[$prospectFieldCustom->field_id];
			if ($storageValue->updated_at >= $previousCustomStorageUpdateAt) {
				$results[$thisProspectId][$fieldName] = $value;
				$thisProspectCustomFieldInfo[$prospectFieldCustom->field_id] = $storageValue->updated_at;
			}
		}
		unset($customFieldMostRecentUpdatedAt);
		unset($customFieldStorageValues);

		// Cleans up doctrine objects stored in cache to prevent OOM
		piProspectFieldCustomStorageTable::getInstance()->getRepository()->evictAll();
		piProspectFieldCustomStorageTable::getInstance()->clear();

		return $results;
	}

	/**
	 * @param $fieldType
	 * @return bool
	 */
	protected function isMultiValueDataType($fieldType): bool
	{
		$dataType = DataTypeCatalog::getDataTypeByDatabaseEnum($fieldType);
		return $dataType instanceof ArrayDataType;
	}
}



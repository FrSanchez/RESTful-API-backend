<?php

/**
 * Used by the Import API to construct the "columns" to be passed into the parameters
 * Class ImportColumnHelper
 */
class ImportColumnHelper
{
	/**
	 * @var piProspectFieldDefaultTable
	 */
	private $piProspectFieldDefaultTable;

	/**
	 * @var callable
	 */
	private $fnRetrieveProspectFieldCustomsOrderedByName;

	/**
	 * @var AccountSettingsManager|null
	 */
	protected $accountSettingsManager;

	/** @var int */
	private $accountId;

	/**
	 * @param $accountId int
	 * @param $piProspectFieldDefaultTable null|piProspectFieldDefaultTable Used to mock the piProspectFieldDefaultTable
	 *          in unit tests.
	 * @param $fnRetrieveProspectFieldCustomsOrderedByName null|callable Used to mock piProspectFieldCustomTable::retrieveFieldsOrderedByName in
	 *        unit tests.
	 * @param $accountSettingsManager AccountSettingsManager Mock for tests
	 */
	public function __construct(int $accountId, $piProspectFieldDefaultTable = null, $fnRetrieveProspectFieldCustomsOrderedByName = null, $accountSettingsManager = null)
	{
		$this->accountId = $accountId;
		$this->piProspectFieldDefaultTable = $piProspectFieldDefaultTable ?? piProspectFieldDefaultTable::getInstance();
		$this->fnRetrieveProspectFieldCustomsOrderedByName = $fnRetrieveProspectFieldCustomsOrderedByName ??
			[piProspectFieldCustomTable::class, 'retrieveFieldsOrderedByName'];
		$this->accountSettingsManager = $accountSettingsManager ?? AccountSettingsManager::getInstance($accountId);
	}

	protected function getCustomFieldCollection($accountId)
	{
		return call_user_func($this->fnRetrieveProspectFieldCustomsOrderedByName, $accountId);
	}

	/**
	 * Creates the columns for the Import Jobs from the field names given by the user for the specified account.
	 * @param int $accountId The ID of the account
	 * @param string[] $csvHeader The first row of the CSV file given by the user.
	 * @param bool $overwrite should existing values in the DB be overwritten by import value
	 * @param bool $nullOverwrite should null values in the import overwrite existing DB values
	 * @param array $userColumnOptions user provided values for overwrite and nullOverwrite
	 * @param bool $allowAddToListColumn Determines if the "addToList" column is allowed within the CSV. Defaults to false.
	 * @param bool $allowRemoveFromListColumn Determines if the "removeFromList" column is allowed within the CSV. Defaults to false.
	 * @param bool $useApiFrameworkSuffix
	 * @param bool $allowMatchColumns
	 * @return array
	 * [0] mixed[] associative arrays denoting column information needed by the Import Jobs <br>
	 * [1] string[] array of fields that couldn't be verified
	 */
	public function createImportColumns(
		int   $accountId,
		array $csvHeader,
		bool  $overwrite = false,
		bool  $nullOverwrite = false,
		array $userColumnOptions = [],
		bool  $allowAddToListColumn = false,
		bool  $allowRemoveFromListColumn = false,
		bool  $useApiFrameworkSuffix = false,
		bool  $allowMatchColumns = false
	): array
	{
		// TODO can we pull the list of valid fields from somewhere else?
		// TODO is there a better way to get all of the fields that are updateable/createable?

		/** @var piProspectFieldCustom[]|piProspectFieldDefault[] $requiredFields */
		$requiredFields = [];
		$requiredFieldErrors = [];

		/** @var Doctrine_Collection $customFieldCollection */
		$customFieldCollection = $this->getCustomFieldCollection($accountId);
		$customFieldCollectionMap = [];
		foreach ($customFieldCollection->getIterator() as $customField) {
			if ($customField) {
				$fieldId = $customField->field_id;
				if ($useApiFrameworkSuffix) {
					$fieldId .= ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX;
				}
				$customFieldCollectionMap[strtolower($fieldId)] = $customField;

				if ($customField['is_required']) {
					$requiredFields[] = $customField;
				}
			}
		}

		$fieldDefaultCollection = $this->piProspectFieldDefaultTable->findByAccountId($accountId);
		$fieldDefaultCollectionMap = [];
		foreach ($fieldDefaultCollection->getIterator() as $i => $item) {
			$fieldDefaultCollectionMap[strtolower($item['field'])] = $item;

			if ($item['is_required']) {
				$requiredFields[] = $item;
			}
		}

		$otherFieldMap = [ // Fields on the prospect table that are not listed in the prospect_field_default table.
			'is_reviewed' => ['other_field_type' => FormFieldPeer::TYPE_CHECKBOX],
			'is_starred' => ['other_field_type' => FormFieldPeer::TYPE_CHECKBOX]
		];

		if ($userColumnOptions === null || !is_array($userColumnOptions)) {
			$userColumnOptions = [];
		}

		// The fieldName below is always transformed to lower case
		// therefore the need to change the array keys
		$userColumnOptions = array_change_key_case($userColumnOptions);

		$columns = [];
		$invalidFields = [];
		foreach ($csvHeader as $csvHeaderValue) {
			$csvHeaderValue = strtolower(trim($csvHeaderValue));

			// set the column defaults
			$column = [];
			$column['default_id'] = null;
			$column['custom_id'] = null;
			$column['assigned_user'] = null;
			$column['display_name'] = null;
			$column['is_use_values'] = null;
			$column['is_required'] = null;
			$column['is_validate'] = null;
			//Allows the notes field to overwrite the existing value, which honors API behavior. Standard import behavior is to concatenate.
			$column['replace_note'] = null;

			// overwrite is used only in update cases. if a value is specified in the DB and overwrite is true,
			// the value specified in the input row will overwrite the value in the DB
			$column['overwrite'] = array_key_exists($csvHeaderValue, $userColumnOptions) && isset($userColumnOptions[$csvHeaderValue]['overwrite'])
				? $userColumnOptions[$csvHeaderValue]['overwrite'] : $overwrite;

			// nulloverwrite is used only in update cases. if the value in the input row is null (or empty), the value
			// in the DB will be null (overwriting any existing value in the DB with a null).
			$column['nulloverwrite'] = array_key_exists($csvHeaderValue, $userColumnOptions) && isset($userColumnOptions[$csvHeaderValue]['nullOverwrite'])
				? $userColumnOptions[$csvHeaderValue]['nullOverwrite'] : $nullOverwrite;

			unset($userColumnOptions[$csvHeaderValue]);

			if ($csvHeaderValue === 'campaign_id') {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::CAMPAIGN_ID => ImportColumnParameterConstants::CAMPAIGN_ID,
					'nulloverwrite' => false
				]);

			} else if ($csvHeaderValue === 'score') {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::SCORE => ImportColumnParameterConstants::SCORE,
					'overwrite' => true,     // overwrite is always true for score
					'nulloverwrite' => false // nulloverwrite is never applicable
				]);

			} else if ($csvHeaderValue === 'notes') {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::NOTES => ImportColumnParameterConstants::NOTES,
					'nulloverwrite' => true,
					ImportColumnParameterConstants::REPLACE_NOTE => true
				]);
			} else if ($csvHeaderValue === 'prospect_id') {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::PROSPECT_ID => ImportColumnParameterConstants::PROSPECT_ID,
					'nulloverwrite' => false,
					'overwrite' => false,
				]);
			} else if ($csvHeaderValue === ImportColumnParameterConstants::SALESFORCE_FID) {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::SALESFORCE_FID => ImportColumnParameterConstants::SALESFORCE_FID,
					'nulloverwrite' => false,
					'overwrite' => false,
				]);
			} else if ($allowAddToListColumn && $csvHeaderValue === 'addtolist') {
				unset($column['nulloverwrite']);
				unset($column['overwrite']);
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::ADD_TO_LIST => ImportColumnParameterConstants::ADD_TO_LIST
				]);
			} else if ($allowRemoveFromListColumn && $csvHeaderValue === 'removefromlist') {
				unset($column['nulloverwrite']);
				unset($column['overwrite']);
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::REMOVE_FROM_LIST => ImportColumnParameterConstants::REMOVE_FROM_LIST
				]);
			} else if ($allowMatchColumns && $csvHeaderValue == "matchid") {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::MATCH_ID => ImportColumnParameterConstants::MATCH_ID
				]);
			} else if ($allowMatchColumns && $csvHeaderValue == "matchemail") {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::MATCH_EMAIL => ImportColumnParameterConstants::MATCH_EMAIL
				]);
			} else if ($allowMatchColumns && $csvHeaderValue == "matchsalesforceid") {
				$columns[] = array_replace($column, [
					ImportColumnParameterConstants::MATCH_SALESFORCEID => ImportColumnParameterConstants::MATCH_SALESFORCEID
				]);
			} else if (array_key_exists($csvHeaderValue, $customFieldCollectionMap)) {
				$customField = $customFieldCollectionMap[$csvHeaderValue];
				$columns[] = array_replace($column, [
					'custom_id' => $customField['id'],
					'display_name' => $customField['name'],
					'field_code' => 'c' . $customField['id'],
					'is_use_values' => $customField['is_use_values'],
					'is_required' => $customField['is_required'],
					'is_validate' => $customField['is_validate']
				]);

			} else if (array_key_exists($csvHeaderValue, $fieldDefaultCollectionMap)) {
				$fieldDefault = $fieldDefaultCollectionMap[$csvHeaderValue];
				$nullOverwriteValue = $fieldDefault['type'] == FormFieldPeer::TYPE_CHECKBOX ? false : $column['nulloverwrite'];
				$columns[] = array_replace($column, [
					'default_id' => $fieldDefault['id'],
					'display_name' => $fieldDefault['name'],
					'field_code' => 'd' . $fieldDefault['id'],
					'is_use_values' => $fieldDefault['is_use_values'],
					'is_required' => $fieldDefault['is_required'],
					'is_validate' => $fieldDefault['is_validate'],
					// This can only be safely done in default fields, custom fields may have different values for the checkbox
					'nulloverwrite' => $nullOverwriteValue
				]);

			} else if (array_key_exists($csvHeaderValue, $otherFieldMap)) {
				$fieldInfo = $otherFieldMap[$csvHeaderValue];
				$columns[] = array_replace($column, [
					'other_field_id' => $csvHeaderValue,
					'display_name' => $csvHeaderValue,
					'field_code' => 'd' . $csvHeaderValue,
					'other_field_type' => $fieldInfo['other_field_type']
				]);

			} else {
				$invalidFields[] = $csvHeaderValue;
			}
		}

		if ($this->accountSettingsManager::accountHasFeatureEnabled($accountId, AccountSettingsConstants::PROSPECT_REQUIRED_FIELD_CONTROL) &&
			$this->accountSettingsManager::accountHasFeatureEnabled($accountId, AccountSettingsConstants::PROSPECT_REQUIRED_FIELD_IMPORT)) {
			//If field control and required fields in imports have been enabled, validate that every required field is present.
			foreach ($requiredFields as $requiredField) {
				$customField = false;
				if ($requiredField instanceof piProspectFieldCustom) {
					$customField = true;
				}

				$fieldId = $requiredField->id;
				$foundColumn = false;

				foreach ($columns as $column) {
					if ($customField && $column['custom_id'] == $fieldId) {
						$foundColumn = true;
					} else if (!$customField && $column['default_id'] == $fieldId) {
						$foundColumn = true;
					}

					if ($foundColumn) {
						break;
					}
				}

				if (!$foundColumn) {
					//At least one required field is not present in the CSV. Error.
					$requiredFieldErrors[] = $requiredField;
				}
			}
		}

		$invalidUserColumns = [];
		if (!empty($userColumnOptions)) {
			$invalidUserColumns = array_keys($userColumnOptions);
		}

		return [
			$columns,
			$invalidFields,
			$invalidUserColumns,
			$requiredFieldErrors
		];
	}
}

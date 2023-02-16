<?php
class ProvisionedAccountTypeHelper {
	const PROVISIONED_ACCOUNT_TYPE_DEMO = "Demo";
	// if you change production to a different value change the default in accountSettingsDefaults.yml
	const PROVISIONED_ACCOUNT_TYPE_PRODUCTION = "Production";
	const PROVISIONED_ACCOUNT_TYPE_SANDBOX = "Sandbox";


	/**
	 * @param int $accountId
	 * @return string
	 */
	public static function getProvisionedAccountType($accountId) {
		return AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SETTING_PROVISIONED_ACCOUNT_TYPE);
	}

	/**
	 * @param int $accountId
	 * @param value $value
	 * @param boolean $ignoreEmptyRequirement
	 * @return string
	 */
	public static function setProvisionedAccountType($accountId, $value, $ignoreEmptyRequirement = false) {
		$accountSettingsManager = AccountSettingsManager::getInstance($accountId);
		if (empty($accountSettingsManager->getValue(AccountSettingsConstants::SETTING_PROVISIONED_ACCOUNT_TYPE)) || $ignoreEmptyRequirement) {
			$accountSettingsManager->setValue(AccountSettingsConstants::SETTING_PROVISIONED_ACCOUNT_TYPE, $value);
		}
	}

}

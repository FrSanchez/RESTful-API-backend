<?php

class C2cConnectionHelper
{

	const SANDBOX_LOGIN_URL = "test.salesforce.com";

	const PROD_LOGIN_URL = "login.salesforce.com";
	const PROD_GDOT_CORE_TENANT_PREFIX = "core/prod/";

	const STMPA_LOGIN_URL = "login.stmpa.stm.salesforce.com";
	const STMPA_GDOT_CORE_TENANT_PREFIX = "core/mobile1/";

	const STMPB_LOGIN_URL = "login.stmpb.stm.salesforce.com";
	const STMPB_GDOT_CORE_TENANT_PREFIX = "core/mobile2/";

	public static function deriveOrgTenantKey($account, $orgId){
		$login_url = AccountSettingsManager::getInstance($account->id)->getValue(AccountSettingsConstants::SALESFORCE_API_ENVIRONMENT);
		$coreTenantKey = null;
		if (strpos($login_url, self::PROD_LOGIN_URL) !== false) {
			$coreTenantKey = self::PROD_GDOT_CORE_TENANT_PREFIX . $orgId;
		} else if (strpos($login_url, self::STMPA_LOGIN_URL) !== false)  {
			$coreTenantKey = self::STMPA_GDOT_CORE_TENANT_PREFIX . $orgId;
		} else if (strpos($login_url, self::STMPB_LOGIN_URL) !== false) {
			$coreTenantKey = self::STMPB_GDOT_CORE_TENANT_PREFIX . $orgId;
		} else if (strpos($login_url, self::SANDBOX_LOGIN_URL) !== false) {
			$coreTenantKey = self::PROD_GDOT_CORE_TENANT_PREFIX . $orgId;
		}

		return $coreTenantKey;
	}

	/**
	 * for full org tenant key -
	 * returns array of ['core', '<coreEnv>', '<orgId>']
	 *
	 * for full pardot tenant key -
	 * returns array of ['pardotcloud', '<coreEnv>', '<orgId>_<accountId>']
	 *
	 * returns false for an invalid tenant key
	 * @param $tenantKey
	 * @return array
	 */
	public static function explodeTenantKey($tenantKey){
		$ex = explode("/", $tenantKey);
		return count($ex) == 3 ? $ex : false;
	}

	public static function getOrgTenantKey($accountId){
		return \AccountSettingsManager::getInstance($accountId)->getValue(\AccountSettingsConstants::SETTING_C2C_FULL_ORG_TENANT_KEY);
	}

	public static function getPardotTenantKey($accountId){
		return \AccountSettingsManager::getInstance($accountId)->getValue(\AccountSettingsConstants::SETTING_C2C_FULL_PARDOT_TENANT_KEY);
	}

	public static function clearTenantKeysAndVerifiedTimestamp($accountId){
		$asm = \AccountSettingsManager::getInstance($accountId);
		$asm->setValue(\AccountSettingsConstants::SETTING_C2C_FULL_ORG_TENANT_KEY, "");
		$asm->setValue(\AccountSettingsConstants::SETTING_C2C_FULL_PARDOT_TENANT_KEY, "");
		$asm->setValue(\AccountSettingsConstants::SETTING_C2C_TRUST_VERIFIED_AT, "");
	}

}
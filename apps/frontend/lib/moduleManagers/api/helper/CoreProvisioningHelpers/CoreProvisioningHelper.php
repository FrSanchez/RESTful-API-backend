<?php

use Pardot\Job\Manager\Populator\PopulatorRequestSenderInfo;
use Pardot\Job\Manager\Populator\PopulatorRequestSenderInfoCollection;
use Pardot\Job\Manager\Populator\PopulatorRequestSenderFactory;

class CoreProvisioningHelper extends CoreProvisioningHelperBase
{
	/**
	 * License the tenant
	 *
	 * @param $accountId
	 * @param $transformedPayload
	 * @return bool whether or not it succeeded, currently it always succeeds unless an sfStopException is thrown
	 * @throws sfStopException throws sfStopException on error
	 */
	public function licenseTenant($accountId, $transformedPayload)
	{
		try {

			$accountProvisioningManager = new AccountProvisioningManager(AccountProvisioningManager::SALESFORCE_PROVISIONING_USER, $transformedPayload, $accountId);
			if (!$accountProvisioningManager->activateLicense(true)) {
				$this->returnJsonError("error licensing tenant", ProvisioningStatusCodes::UNKNOWN_ERROR);
				return false;
			}

		} catch (AccountProvisioningException $e) {
			$this->returnJsonError("licensing error: ".$e->getMessage(), ProvisioningStatusCodes::UNKNOWN_ERROR);
			return false;
		} catch (SalesEdgeProvisioningException $e) {
			$this->returnJsonError("SalesEdge licensing error: ".$e->getMessage(), ProvisioningStatusCodes::UNKNOWN_ERROR);
			return false;
		} catch (Exception $e) {
			$this->returnJsonError("licensing exception: ".$e->getMessage(), ProvisioningStatusCodes::UNKNOWN_ERROR);
			return false;
		}
		$this->setCoreLicenseType($accountId, $transformedPayload);
		$this->updateFakeBillingInfo($accountId);

		return true;
	}

	public function setUpOfekInternalApiService($accountId, $transformedPayload)
	{
		try {

			if (isset($transformedPayload['pardot_einstein'])) {
				//get PardotEinstein Perm value
				$pardotEinstein = $transformedPayload['pardot_einstein'];
				// check if an entry for ofek service exists in the internal_api_service_table
				$ofekService = piInternalApiConsumerTable::getInstance()->retrieveOneByServiceName($accountId, self::OFEK_SERVICE_NAME);
				if ($pardotEinstein > 0 && !$ofekService) {
					// pardot Einstein perm is on and no entry is in internal_api_service. If an entry already exist do nothing
					piInternalApiConsumerTable::getInstance()->createServiceEntry($accountId, self::OFEK_SERVICE_NAME, \Pardot\Constants\ShardDb\InternalApiConsumer\KeyRetrievalPolicyConstants::MANUAL , self::OFEK_KEY_RETRIEVAL_DATA);
				}
				if ($pardotEinstein == 0 && $ofekService) {
					// pardot Einstein perm is off and an entry is in internal_api_service. Delete the entry
					piInternalApiConsumerTable::getInstance()->archiveByServiceName($accountId, self::OFEK_SERVICE_NAME);
				}
				return true;
			}
		} catch (Exception $e) {
			$this->returnJsonError("Ofek Internal Api Service exception: ".$e->getMessage(), ProvisioningStatusCodes::UNKNOWN_ERROR);
		}
	}


	/**
	 * Transforms the payload into the format AccountProvisioningManager accepts
	 *
	 * @param $payload
	 * @return array
	 */
	public function transformPayload($payload)
	{
		$transform = [
			'org_id' => $payload['orgId'],
			'bpo_id' => $payload['bpoId'],
			'api_version' => $this->getValueFromPayload($payload, 'apiVersion',
				SalesforceApiVersionChecker::DEFAULT_SF_CONNECTOR_API_VERSION),
			'pardot' => $this->getValueFromPayload($payload, 'Pardot', 1),
			'pardot_advanced' => $this->getValueFromPayload($payload, 'PardotAdvanced'),
			'pardot_enterprise' => $this->getValueFromPayload($payload, 'PardotEnterprise'),
			'pardot_einstein' => $this->getValueFromPayload($payload, 'PardotEinstein'),
			'pardot_plus' => $this->getValueFromPayload($payload, 'PardotPlus'),
			'pardot_growth' => $this->getValueFromPayload($payload, 'PardotGrowth'),
			'company' => $this->getValueFromPayload($payload, 'Company', 'Default Company Name'),
			'timezone' => $this->getValueFromPayload($payload, 'Timezone', 'America/New_York'),
			// Use the teamhydra email address so we'll get emailed if this ever happens
			'email' => $this->getValueFromPayload($payload, 'AdminEmail', null),
			'username' => $this->getValueFromPayload($payload, 'AdminUsername', null),
			'first_name' => $this->getValueFromPayload($payload, 'AdminFirstName', ''),
			'last_name' => $this->getValueFromPayload($payload, 'AdminLastName', ''),
			'isDeleted' => $this->getValueFromPayload($payload, 'isDeleted', 0),
			'crm_user_fid' => $this->getValueFromPayload($payload, 'AdminUserFid', null),
			'crm_profile_id' => $this->getValueFromPayload($payload, 'AdminProfileId', null),
			'system_admin_profile_id' => $this->getValueFromPayload($payload, 'SystemAdministratorProfileId', null),
			'is_sdo' => $this->getValueFromPayload($payload, 'isSdo', false),
			'is_sandbox' => $this->getValueFromPayload($payload, 'IsSandbox', false),
			'pardot_template_id' => $this->getValueFromPayload($payload, 'PardotTemplateId', null),
			'timestamp' => $this->getValueFromPayload($payload, 'timestamp', 0),
			'orgStatus' => $this->getValueFromPayload($payload, 'OrgStatus', null),
			'login_url' => $this->getValueFromPayload($payload, 'LoginUrl', null),
			'pardot_sandbox_tenant_limit' => $this->getValueFromPayload($payload, 'PardotSandboxTenantLimit', null),
			'sandbox_parent_org' => $this->getValueForSandboxParentOrg($payload),
			'account_id' => $this->getValueFromPayload($payload, 'AccountId', null),
			'has_email_send_time_optimization' => $this->getValueFromPayload($payload, 'PardotOptimSendTime', null),
			'has_customer_encryption_key' => $this->getValueFromPayload($payload, 'PardotCustomerEncryptionKey', null),
			'has_einstein_engagement_frequency' => $this->getValueFromPayload($payload, 'PardotEinsteinEngageFreq', null),
		];
		$type = $this->getAccountType($transform);
		$transform['type'] = $type;
		$transform = array_merge($transform, $this->getAccountLimits($payload, $type));

		$logSafeTransform = $transform;
		$logSafeTransform['email'] = "---";
		$logSafeTransform['username'] = "---";
		$logSafeTransform['company'] = "---";
		$logSafeTransform['first_name'] = "---";
		$logSafeTransform['last_name'] = "---";
		debugTools::logInfo("Provisioning transformed payload: ".serialize($logSafeTransform));
		return $transform;
	}

	/**
	 * Gets the default Account Limits for the given Account Type, then overwrites them with values sent over the line.
	 *
	 * @param $payload
	 * @param $type
	 * @return array
	 */
	private function getAccountLimits($payload, $type)
	{
		$defaults = piAccountLimitTable::getAccountLimitDefaults($type);
		// Defaults Key => Payload Key
		$overrideFieldMapping = [
			'max_landing_pages' => 'PardotLandingPages',
			'max_keywords' => 'PardotSeoLimit',
			'max_db_size' => 'PardotProspectsLimit',
			'max_custom_objects' => 'PardotCustomObjectLimit',
			'max_site_search_urls' => 'PardotSiteSearchUrls',
			'max_prospect_field_customs' => 'PardotCustomFields',
			'max_page_actions' => 'PardotPageActionsLimit',
			'max_api_requests' => 'PardotApiLimit',
			'max_form_handlers' => 'PardotForms',
			'max_forms' => 'PardotForms',
			'max_automations' => 'PardotAutomationRulesLimit',
			'max_competitors' => 'PardotCompetitorLimit',
			'max_drip_programs' => 'PardotDripPrograms',
			'max_file_storage_size' => 'PardotFileLimit',
			'has_social_data' => 'PardotSocial',
			'has_dedicated_email_ip' => 'PardotDedicatedIPAddress',
			'has_custom_roles' => 'PardotCustomUserRoles',
			'has_dynamic_content' => 'PardotDynamicContent',
			'has_multivariate_tests' => 'PardotMultivariateTesting',
			'has_chat_support_access' => 'PardotChatSupport',
			'has_marketing_calendar' => 'PardotMarketingCalendar',
			'has_phone_access' => 'PardotPhoneSupport',
			'has_paid_search' => 'PardotAdwordsIntegration',
			'has_vanity_url_access' => 'PardotCustomizableUrls',
			'has_litmus_access' => 'PardotEmailAnalytics',
			'has_permanent_bcc' => 'PardotBCCCompliance',
			'num_engage_licenses' => 'PardotEngageLicenses',
			'max_new_external_activity_per_day' => 'PardotExtActApiDailyLimit',
		];
		$hardValues = [
			'max_profiles' => 999999999,
			'max_test_list_members' => 100,
			'max_dynamic_lists' => 9999,
			'max_emails' => 999999999,
			'max_lists' => 999999999,
			'max_personalizations' => 999999999,
			'max_filters' => 999999999,
			'max_users' => 999999999,
			'max_test_lists' => 999999999
		];

		// Get the value from the payload if it's there. Otherwise, use the default for the type
		foreach($overrideFieldMapping as $defaultsKey=>$payloadKey) {
			if (array_key_exists($defaultsKey, $defaults)) {
				$defaults[$defaultsKey] = $this->getValueFromPayload($payload, $payloadKey, $defaults[$defaultsKey]);
			} else {
				$defaults[$defaultsKey] = $this->getValueFromPayload($payload, $payloadKey);
			}

		}

		return array_merge($defaults, $hardValues);
	}

	protected function getValueForSandboxParentOrg($payload) {
		if(array_key_exists('IsSandbox', $payload) && $payload['IsSandbox'] == true) {
			return $this->getValueFromPayload($payload, 'SandboxParentOrg', null);
		} else {
			return null;
		}
	}

	/**
	 * Grabs a value from the payload, used to transform the payload
	 *
	 * @param $payload
	 * @param $key
	 * @param $default
	 * @return int
	 */
	public function getValueFromPayload($payload, $key, $default = 0)
	{
		if (!array_key_exists($key, $payload)) {
			return $default;
		}

		$value = $payload[$key];

		if (is_numeric($value)) {
			// If the value is numeric, return the intval of it to make comparisons simpler, because PHP
			return intval($value);
		}

		return $value;
	}

	/**
	 * Returns true if the payload is valid
	 *
	 * @param $payload
	 * @return bool
	 */
	public function validatePayload($payload)
	{
		if(!$this->validateSandboxParams($payload)) {
			return false;
		}

		$notNullKeys = ["orgId", "bpoId", "timestamp"];

		foreach ($notNullKeys as $key) {
			if (!(array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== "")) {
				debugTools::logError("Core Provisioning Error: field not available or null: $key");
				return false;
			}
		}

		return true;
	}

	/**
	 * We require SandboxParentOrg to be populated on sandbox requests.
	 * but if SandboxParentOrg is set but isSandbox is false, ignore the SandboxParentOrg and create a production org
	 *
	 * @param array $payload
	 * @return bool
	 */
	protected function validateSandboxParams($payload) {
		$sandboxExistsAndIsTrue = array_key_exists('IsSandbox', $payload) && $payload['IsSandbox'];
		$parentOrgFidDoesNotExistOrIsNull = !array_key_exists('SandboxParentOrg', $payload) ||  is_null($payload['SandboxParentOrg']);
		$sandboxDoesNotExistOrIsFalsey = !array_key_exists('IsSandbox', $payload) || !$payload['IsSandbox'];
		$parentOrgFidExistsAndIsPopulated = array_key_exists('SandboxParentOrg', $payload) && !is_null($payload['SandboxParentOrg']);

		if($sandboxExistsAndIsTrue && $parentOrgFidDoesNotExistOrIsNull) {
			PardotLogger::getInstance()->error("Core Provisioning Error: SandboxParentOrg cannot be null on sandbox requests");
			return false;
		}

		if($sandboxDoesNotExistOrIsFalsey && $parentOrgFidExistsAndIsPopulated) {
			PardotLogger::getInstance()->error("Core Provisioning Error: SandboxParentOrg populated on a non-sandbox request");
		}
		return true;
	}

	/**
	 * Returns true if the org is not of type "DotOrg" or "SigningUp", which are used by trialforce templates and pool orgs
	 *
	 * @param $payload
	 */
	public function validateOrgStatus($status)
	{
		if(in_array($status, self::INVALID_SIGNUP_ORG_STATUS)){
			debugTools::logError("Core Provisioning Error: Org status $status is invalid for provisioning.");
			return false;
		}
		return true;

	}


	/**
	 * Returns true if there is an existing pardot tenant or if the payload contains a valid username and email
	 *
	 * @param $sfdcPardotTenant
	 * @param $transformedPayload
	 * @return bool
	 */
	public function validateUsernameAndEmail($sfdcPardotTenant, $transformedPayload, $validPayloadAccountId) {
		if(empty($sfdcPardotTenant) && !$validPayloadAccountId){
			if (
				(!(array_key_exists('email', $transformedPayload) && $transformedPayload['email'] !== null && $transformedPayload['email'] !== "")) ||
				(!(array_key_exists('username', $transformedPayload) && $transformedPayload['username'] !== null && $transformedPayload['username'] !== ""))
			){
				debugTools::logError("Core Provisioning Error: field not available or null: AdminEmail & AdminUsername");
				return false;
			}
		}
		return true;
	}

	/**
	 * @param $payload
	 * Unsets any keys in the payloads that are user configurable to ensure we aren't making changes to user's settings
	 * that they might not want.
	 */
	public static function filterPayloadSettingsForExistingAccounts(&$payload) {
		foreach (self::MODIFIABLE_ACCOUNT_LIMITS as $key){
			unset($payload[$key]);
		}
	}

	/**
	 * Ensure the provided timestamp is greater than the last timestamp to prevent replay attacks
	 *
	 * @param $timestamp
	 * @param $sfdcPardotTenant
	 * @return bool
	 */
	public function validateTimestamp($timestamp, $sfdcPardotTenant)
	{
		// Make sure timestamp is something reasonable
		if ($timestamp == null || $timestamp <= 0) {
			return false;
		}

		return $timestamp > $sfdcPardotTenant->last_timestamp;
	}

	public function validatePayloadAccountId($sfdcPardotTenant, $transformedPayload) {
		if(!empty($sfdcPardotTenant) &&
			array_key_exists('account_id', $transformedPayload) &&
			$transformedPayload['account_id'] !== null &&
			$transformedPayload['account_id'] !== "" &&
			$transformedPayload['account_id'] == $sfdcPardotTenant->account_id) {
			// if we have an accountId passed from core and it matches what we have in sfdcPardotTenant, return true
			return true;
		} elseif(empty($sfdcPardotTenant) &&
			array_key_exists('account_id', $transformedPayload) &&
			$transformedPayload['account_id'] !== null &&
			$transformedPayload['account_id'] !== "") {
			// if we have an accountId passed from core but we don't have an sfdcPardotTenant entry matching the bpo/org, return true
			return true;
		}
		return false;
	}


	/**
	 * Returns true if the tenant is invalid
	 *
	 * @param piSfdcPardotTenant
	 * @return bool
	 */
	public function tenantIsInvalid($sfdcPardotTenant)
	{
		return $sfdcPardotTenant->status === piSfdcPardotTenant::STATUS_INVALID;
	}

	public function getAccountType($transformedPayload)
	{
		if ($this->valueIsTrueOr1($transformedPayload['pardot_advanced'])
			|| $this->valueIsTrueOr1($transformedPayload['pardot_enterprise'])) {
			return AccountConstants::TYPE_SFDC_ULTIMATE_BY_DBSIZE;
		} elseif ($this->valueIsTrueOr1($transformedPayload['pardot_plus'])) {
			return AccountConstants::TYPE_SFDC_PRO_BY_DBSIZE;
		} elseif ($this->valueIsTrueOr1($transformedPayload['pardot_growth'])) {
			return AccountConstants::TYPE_SFDC_STANDARD_BY_DBSIZE;
		} else {
			return AccountConstants::TYPE_SFDC_PROVISIONING_IN_PROGRESS;
		}
	}

	public function getCoreLicenseTypeValue($transformedPayload)
	{

		if ($this->valueIsTrueOr1($transformedPayload['pardot_advanced'])) {
			return self::TYPE_ADVANCED;
		} elseif ($this->valueIsTrueOr1($transformedPayload['pardot_plus'])) {
			return self::TYPE_PLUS;
		} elseif ($this->valueIsTrueOr1($transformedPayload['pardot_growth'])) {
			return self::TYPE_GROWTH;
		} elseif ($this->valueIsTrueOr1($transformedPayload['pardot_enterprise'])) {
			return self::TYPE_ENTERPRISE;
		} else {
			return '';
		}
	}

	public static function isV2ProvisioningAttempt($transformedPayload)
	{
		return isset($transformedPayload['pardot_advanced']) &&
			isset($transformedPayload['pardot_plus']) &&
			isset($transformedPayload['pardot_growth']) &&
			isset($transformedPayload['pardot_enterprise']) &&
			isset($transformedPayload['bpo_id']);
	}

	public function setCoreLicenseType($accountId, $transformedPayload)
	{
		AccountSettingsManager::getInstance($accountId)->setValue(
			AccountSettingsConstants::SETTING_CORE_PARDOT_LICENSE_TYPE,
			$this->getCoreLicenseTypeValue($transformedPayload));
	}

	public function getCoreLicenseTypeFromAccountSettings($accountId)
	{
		return AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SETTING_CORE_PARDOT_LICENSE_TYPE);
	}

	public function getCoreLicenseLabel($accountId)
	{
		$value = AccountSettingsManager::getInstance($accountId)->getValue(
			AccountSettingsConstants::SETTING_CORE_PARDOT_LICENSE_TYPE);

		$i18n = sfI18N::getInstance();
		switch ($value) {
			case self::TYPE_ADVANCED:
				return $i18n->__('Account.Constants.Core.Type.Advanced');
			case self::TYPE_PLUS:
				return $i18n->__('Account.Constants.Core.Type.Plus');
			case self::TYPE_GROWTH:
				return $i18n->__('Account.Constants.Core.Type.Growth');
			case self::TYPE_ENTERPRISE:
				return $i18n->__('Account.Constants.Core.Type.Enterprise');
			default:
				return false;

		}
	}

	/**
	 * This method determines whether account has Pardot Advanced or Enterprise edition license based on parameters
	 * received in a v2 provisioning request or previously provisioned account settings for the specified account
	 *
	 * @param $transformedPayload - associative array of params received in v2 provisioning request or empty array
	 * if called from context other than provisioning
	 * @param int|null $accountId - specifies the account id when called from context other than provisioning.  Specify
	 * null if called from provisioning context
	 * @return bool
	 */
	public function isAdvancedOrEnterpriseLicenseType($transformedPayload, $accountId = null)
	{
		$isCriteriaMet = (array_key_exists('pardot_advanced', $transformedPayload) && strcmp($this->getCoreLicenseTypeValue($transformedPayload), self::TYPE_ADVANCED) === 0)
			|| (array_key_exists('pardot_enterprise', $transformedPayload) && strcmp($this->getCoreLicenseTypeValue($transformedPayload), self::TYPE_ENTERPRISE) === 0);

		if (!$isCriteriaMet && $accountId) {
			$licenseType = $this->getCoreLicenseTypeFromAccountSettings($accountId);
			$isCriteriaMet = ($licenseType === self::TYPE_ADVANCED || $licenseType === self::TYPE_ENTERPRISE);
		}
		return $isCriteriaMet;
	}

	public function setAccountSettings($accountId, $transformedPayload)
	{
		$featuresToSet = [];
		if ($this->isAdvancedOrEnterpriseLicenseType($transformedPayload)) {
			$featuresToSet['feature.' . AccountSettingsConstants::FEATURE_ENABLE_SELECTIVE_SYNC] = true;
		}

		$estoFlagValue = $this->getFlagValueFromPayload(
			(int) $accountId,
			'has_email_send_time_optimization',
			AccountSettingsConstants::FEATURE_EMAIL_PARDOT_ESTO_ORG_PERMISSION,
			$transformedPayload
		);

		if (!is_null($estoFlagValue)){
			$featuresToSet['feature.' . AccountSettingsConstants::FEATURE_EMAIL_PARDOT_ESTO_ORG_PERMISSION] = $estoFlagValue;
		}

		$eefFlagValue = $this->getFlagValueFromPayload(
			(int) $accountId,
			'has_einstein_engagement_frequency',
			AccountSettingsConstants::FEATURE_EMAIL_PARDOT_EEF_ORG_PERMISSION,
			$transformedPayload
		);

		if (!is_null($eefFlagValue)){
			$featuresToSet['feature.' . AccountSettingsConstants::FEATURE_EMAIL_PARDOT_EEF_ORG_PERMISSION] = $eefFlagValue;
		}

		$customerEncryptionKeyFlagValue = $this->getFlagValueFromPayload(
			(int) $accountId,
			'has_customer_encryption_key',
			AccountSettingsConstants::FEATURE_ENABLE_BYOK,
			$transformedPayload
		);

		if (!is_null($customerEncryptionKeyFlagValue)){
			$featuresToSet['feature.' . AccountSettingsConstants::FEATURE_ENABLE_BYOK] = $customerEncryptionKeyFlagValue;
		}

		$asm = AccountSettingsManager::getInstance($accountId);

		$this->setSalesforceApiOverrides($transformedPayload, $asm);

		foreach ($featuresToSet as $key => $value)
		{
			$asm->setValue($key, $value);
		}
		$asm->flushSettingsToDatabase();

		$this->setCoreLicenseType($accountId, $transformedPayload);
	}

	public function setSalesforceApiOverrides($accountParams, $asm)
	{
		if (sfConfig::get('app_provisioning_should_apply_routing_overrides') || EnvironmentManager::isPardot3Environment()) { // Do not apply these settings in pi0
			if (array_key_exists('login_url', $accountParams) && $accountParams['login_url'] !== null && $accountParams['login_url'] !== "") {
				$loginUrl = rtrim($accountParams['login_url'], '/');
				$asm->setValue(AccountSettingsConstants::SALESFORCE_API_ENVIRONMENT, $loginUrl);
				$asm->setValue(SalesforceOauthConstants::OAUTH_AUTHORIZE_URL_OVERRIDE, $loginUrl . self::AUTH_URL_SUFFIX);
				$asm->setValue(SalesforceOauthConstants::OAUTH_TOKEN_URL_OVERRIDE, $loginUrl . self::TOKEN_URL_SUFFIX);
				$asm->setValue(SalesforceOauthConstants::OAUTH_INTROSPECT_URL_OVERRIDE, $loginUrl . self::INTROSPECT_URL_SUFFIX);
				$asm->setValue('feature.' . AccountSettingsConstants::FEATURE_CONNECTED_APP_SETTINGS_OVERRIDE, true);
			}
		}
	}

	/**
	 * @param int $accountId
	 * @param string $payloadKey
	 * @param string $featureFlag
	 * @param array $payload
	 * @return bool|null
	 */
	private function getFlagValueFromPayload(int $accountId, string $payloadKey, string $featureFlag, array $payload): ?bool
	{
		if (!array_key_exists($payloadKey, $payload)) {
			return null;
		}

		$payloadValue = $payload[$payloadKey];
		if ($this->valueIsTrueOr1($payloadValue)) {
			PardotLogger::getInstance()->info(
				sprintf('Enabling feature %s for account id %s.',
					$featureFlag,
					$accountId
				)
			);

			return true;
		} else if ($this->valueIsFalseOr0($payloadValue)) {
			PardotLogger::getInstance()->info(
				sprintf('Disabling feature %s for account id %s.',
					$featureFlag,
					$accountId
				)
			);

			return false;
		} else {
			PardotLogger::getInstance()->info(
				sprintf('Null or unrecognized payload value %s, not changing feature %s for account id %s.',
					$payloadValue,
					$featureFlag,
					$accountId
				)
			);
		}

		return null;
	}

	public function changeShardByShardId($shardId)
	{
		$shardManager = ShardManager::getInstance();
		$originalShardId = $shardManager->getCurrentConnectedShardId();
		if ($originalShardId !== $shardId) {
			$shardManager->connectToShardDbByShardId($shardId);
		}
		return $originalShardId;
	}

	public function changeShardByAccountId($accountId)
	{
		return $this->changeShardByShardId(piGlobalAccountTable::getInstance()->getShardIdByAccountId($accountId));
	}

	public function startJobsForAccount($accountId)
	{
		$jobsToStart = [
			SalesforceConnectorV2VerificationJobHandler::class,
			SystemEmailV2JobHandler::class,
			FolderWarmupJobHandler::class
		];

		foreach ($jobsToStart as $jobToStart) {
			try {
				$info = PopulatorRequestSenderInfo::newBuilder()
					->withJobClassName($jobToStart)
					->withAccountId($accountId)
					->build();
				$infoCollection = new PopulatorRequestSenderInfoCollection([$info]);
				$sender = PopulatorRequestSenderFactory::getInstance()->createByAccountId($accountId);
				if ($sender->sendJobPopulationRequests($infoCollection) !== 1) {
					debugTools::logError("Error starting job $jobToStart on Core Provisioning for account $accountId");
				}
			} catch (Exception $e) {
				debugTools::logError("Exception thrown when starting job $jobToStart on Core Provisioning for account $accountId: " . $e->getMessage());
			}
		}
	}

	/**
	 * This method determines whether MBUS_ACCOUNT_SWITCHER feature flag should be enabled for the account based on
	 * parameters received in a v2 provisioning request or previously provisioned account settings for the specified account
	 *
	 * @param mixed[] $accountParams - specifies associative array carrying transformed payload from v2 provisioning
	 * request or empty array if called from other context besides provisioning
	 * @param bool $isMbusNamespacingCriteriaMet - specify true if determineIfTurnOnMbusNamespacing() returned true when
	 * called from v2 provisioning request context or if MBUS namespacing enablement criteria is newly met for an
	 * existing non-MBUS account as determined by the ConvertUsersToMbusJobHandler
	 * @param int|null $accountId - specifies the account id if called from context other than provisioning.  Specify
	 * null if called from provisioning context
	 *
	 * @return bool
	 *
	 * @see CoreProvisioningHelper::determineIfTurnOnMbusNamespacing()
	 * @see ConvertUsersToMbusJobHandler
	 */
	public function determineIfTurnOnMbusAccountSwitcher($accountParams, $isMbusNamespacingCriteriaMet, $accountId = null)
	{
		$isCriteriaMet = false;
		if ($isMbusNamespacingCriteriaMet) {
			$orgId = $accountParams['org_id'] ?? null;
			$isCriteriaMet  = (($orgId || $accountId) && $this->isAdvancedOrEnterpriseLicenseType($accountParams, $accountId));
			$idText = $accountId ?? (is_null($orgId) ? "with no org id" : "with " . $orgId);
			$contextText = (is_null($accountId) ? "Provisioning" : "Job System");
			$enabledText = ($isCriteriaMet ? "be" : "not be");
			$reasonText = (($isCriteriaMet || (is_null($orgId) && is_null($accountId))) ? "" : " because not advanced or not enterprise");
			PardotLogger::getInstance()->info("{CoreProvisioningHelper}: $contextText: determined MBUS account switcher should $enabledText enabled for account " . $idText . $reasonText);
		}
		return $isCriteriaMet;
	}

	/**
	 * Billing info not something that needs to be tracked in Pardot anymore.
	 * However, this is a required field on the Root version of the Account Edit page.
	 * In order to not have that page error anytime it needs to be messed with, we'll just fill in these deprecated fields.
	 *
	 * @param $accountId
	 * @throws Exception
	 */
	private function updateFakeBillingInfo($accountId)
	{
		$account = piAccountTable::getInstance()->findOneById($accountId);
		$account->billing_date = dateTools::dateOnlyFormat(strtotime('now'));
		$account->expiration = dateTools::dbFormat(strtotime('+1 year'));
		$account->email_overage_rate = floatval(0.01);
		$account->save();
	}

	/**
	 * For Show Pardot Tenants Page. You must provide a work ID
	 *
	 * This should be a single Salesforce Org Id, that does not currently have a pardot account
	 *
	 * the pardot account id to access this is 676863 in production or 162501 in pi.demo
	 *
	 * @return string
	 */
	public static function getAliasedOrg($accountId)
	{
		return AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SALESFORCE_PROXY_ORG);
	}

	/**
	 * For Show Pardot Tenants Page. You must provide a work ID
	 *
	 * This should be a Pardot Account Id
	 * @return array
	 */
	public static function getAllowlistedPardotAccountsForArchivedAtClearing()
	{
		return [
			597231, // W-8193458 - aid 597231 - backfill not working, need to clear archived at so this customer isn't archived
			589013, // case 34933791 - prevent archiving of the account to give time to let SOPs sort out their licensing issues
		];
	}

	/**
	 * @param bool $isSdo
	 * @param bool $isSandbox
	 * @param $transformedPayload
	 * @param int $accountId
	 */
	public function setAccountType($isSdo, $isSandbox, $accountId) {
		if ($isSdo) {
			ProvisionedAccountTypeHelper::setProvisionedAccountType($accountId, ProvisionedAccountTypeHelper::PROVISIONED_ACCOUNT_TYPE_DEMO);
		} else if($isSandbox) {
			ProvisionedAccountTypeHelper::setProvisionedAccountType($accountId, ProvisionedAccountTypeHelper::PROVISIONED_ACCOUNT_TYPE_SANDBOX);
		} else {
			//else it must be a production account
			ProvisionedAccountTypeHelper::setProvisionedAccountType($accountId, ProvisionedAccountTypeHelper::PROVISIONED_ACCOUNT_TYPE_PRODUCTION);
		}
	}

	/**
	 * @param $accountId
	 * @return bool
	 */
	public function getShouldHaveEmailAbTesting($accountId) {
		return $this->getCoreLicenseTypeFromAccountSettings($accountId) == self::TYPE_GROWTH;
	}
}

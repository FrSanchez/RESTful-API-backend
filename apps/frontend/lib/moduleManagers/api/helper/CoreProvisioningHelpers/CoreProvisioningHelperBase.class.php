<?php

class CoreProvisioningHelperBase
{
	const STATUS_CREATED = "Created";
	const STATUS_UPDATED = "Updated";
	const STATUS_DELETED = "Deleted";
	const STATUS_ERROR = "Error";
	const STATUS_DEPROVISIONED = "Deprovisioned";
	const STATUS_INVALID = "Invalid";

	//Internal api table Ofek service Name
	const OFEK_SERVICE_NAME = "Ofek";
	const OFEK_KEY_RETRIEVAL_DATA ="ofek";

	const TYPE_ADVANCED = "ADVANCED";
	const TYPE_GROWTH = "GROWTH";
	const TYPE_PLUS = "PLUS";
	const TYPE_ENTERPRISE = "ENTERPRISE";

	const NON_OVERRIDABLE_ACCOUNT_DATA = [
		"company",
		"timezone"
	];

	const MODIFIABLE_ACCOUNT_LIMITS = [
		"has_nonmarketing_email",
		"has_email_opens_adjust_score_once",
		"concurrent_api_requests",
		"max_import_batches_per_day",
		"has_email_blocked"
	];

	const INVALID_SIGNUP_ORG_STATUS = [
		"DOTORG",
		"SIGNING_UP"
	];

	const TOKEN_URL_SUFFIX = "/services/oauth2/token";
	const AUTH_URL_SUFFIX  = "/services/oauth2/authorize";
	const INTROSPECT_URL_SUFFIX  = "/services/oauth2/introspect";

	protected $apiActions;

	public function __construct(&$apiActions = null)
	{
		$this->apiActions = $apiActions;
	}

	/**
	 * Return a JSON error on the API
	 *
	 * @param $message string Error message
	 * @param $statusCode String ProvisioningStatusCodes enum
	 * @throws sfStopException
	 */
	public function returnJsonError($message, $statusCode)
	{
		$knownErrors = [
			"Aggregation failed because only a TRIAL edition license was effective",
			"The org has licenses granted through license editor",
			"QUERY_TIMEOUT",
			"INVALID_SESSION_ID",
			"Invalid Org Status",
			"Org status DOTORG is invalid for provisioning",
			"Org status SIGNING_UP is invalid for provisioning",
		];

		$knownError = false;
		foreach ($knownErrors as $error) {
			if (strpos($message, $error) !== false) {
				$knownError = true;
				break;
			}
		}

		if ($knownError) {
			GraphiteClient::increment("provisioning.coreProvisioning.error_classification.known");
		} else {
			GraphiteClient::increment("provisioning.coreProvisioning.error_classification.unknown");
		}

		debugTools::logError("Core Provisioning Error: $message");
		GraphiteClient::increment("provisioning.coreProvisioning.error." . $statusCode);
		$this->apiActions->jsonOutput = json_encode(["result" => "error", "message" => $message, "statusCode" => $statusCode, "status" => self::STATUS_ERROR]);
	}

	/**
	 * Return a JSON success on the API
	 *
	 * @param $message string Error message
	 * @throws sfStopException
	 */
	public function returnJsonSuccess($accountId, $status)
	{
		PardotLogger::getInstance()->info("Core Provisioning Success: accountId $accountId status $status");
		GraphiteClient::increment("provisioning.coreProvisioning.success." . $status);
		$this->apiActions->jsonOutput = json_encode(["result" => "success",
			"pardotAccountId" => $accountId,
			"status" => $status,
			"pardotGdotTenantKey" => ($status == self::STATUS_DELETED ? "" : $this->getSavedPardotTenantKey($accountId))]);
	}

	protected function getSavedPardotTenantKey($accountId) : String
	{
		try {
			return AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SETTING_C2C_FULL_PARDOT_TENANT_KEY, "");
		} catch (Exception $e) {
			debugTools::logError("Core Provisioning Error: ".$e->getMessage()." :: continuing.");
			return "";
		}
	}

	/**
	 * @param $value
	 * @return bool
	 */
	protected function valueIsTrueOr1($value)
	{
		return $value === 1 || $value === true || $value === "1";
	}

	/**
	 * @param $value
	 * @return bool
	 */
	protected function valueIsFalseOr0($value)
	{
		return $value === 0 || $value === false || $value === "0";
	}

	/**
	 * Returns true if the SKU is on in Core
	 *
	 * @param $transformedPayload
	 * @return bool
	 */
	public function pardotSkuOn($transformedPayload)
	{
		return ($this->valueIsTrueOr1($transformedPayload['pardot_advanced']) || $this->valueIsTrueOr1($transformedPayload['pardot_plus'])
			|| $this->valueIsTrueOr1($transformedPayload['pardot_growth']) || $this->valueIsTrueOr1($transformedPayload['pardot_enterprise']));
	}
}

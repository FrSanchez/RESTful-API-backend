<?php

include_once __DIR__ .'/PermissionSetDefinitions.php';

class IntegrationUserPermissionsHelper
{
	/**
	 * @var SalesforceRestApiAbstract
	 */
	protected $salesforceRestApi;

	/** @var $account piAccount  */
	protected $account;

	/** $salesforceOrgId String */
	protected $salesforceOrgId;

	/** @var AccountSettingsManager */
	protected $accountSettingsManager;

	public function __construct($account)
	{
		$this->salesforceRestApi = null;
		$this->account = $account;
	}

	/**
	 * Method to compare the permissions on the Integration user profile with a defined set of expected permissions.
	 *
	 * @return array
	 * @throws \Pardot\Salesforce\RestApiException
	 * @throws Exception
	 */
	public function checkIntegrationUserProfile()
	{
		$profileIdQuery = "SELECT Id FROM Profile WHERE Name = 'B2BMA Integration User'";
		$profile = $this->runSoqlQueryAsIntegrationUser($profileIdQuery);

		if (empty($profile)) {
			PardotLogger::getInstance()->error("No valid profile for B2BMA Integration User for account " . $this->account->id);
			throw new Exception("No valid profile for B2BMA Integration User for account " . $this->account->id);
		}

		$permissionSetIdQuery = "SELECT Id FROM PermissionSet WHERE ProfileId = '" . $profile->records[0]->Id . "'";
		$permissionSet = $this->runSoqlQueryAsIntegrationUser($permissionSetIdQuery);

		if (empty($permissionSet)) {
			throw new Exception("No valid profile permission set for B2BMA Integration User for account " . $this->account->id);
		}

		$objectCrudQuery = "SELECT PermissionsCreate,PermissionsDelete,PermissionsEdit,PermissionsModifyAllRecords,PermissionsRead,PermissionsViewAllRecords,SobjectType FROM ObjectPermissions WHERE ParentId = '" . $permissionSet->records[0]->Id . "'";
		$objectPermission = $this->runSoqlQueryAsIntegrationUser($objectCrudQuery);

		if (empty($objectPermission)) {
			throw new Exception("No valid object permissions for B2BMA Integration User for account " . $this->account->id);
		}

		$apiVersion = $this->getSalesforceApiVersion();
		if (!$apiVersion === null) {
			throw new Exception("Unable to retrieve the latest API Version");
		}

		$permissionSetDefinition = new PermissionSetDefinitions();
		$profileDefinition = $permissionSetDefinition->getIntegrationUserProfile($apiVersion);

		if ($profileDefinition === null) {
			throw new Exception("Unable to obtain a valid profile definition for API Version " . $apiVersion);
		}

		return $this->validateObjectPermissions($objectPermission, $profileDefinition);
	}

	/**
	 * Given a fixed XML (ULD definition) compare the result of the permission set query and return and escalated discrepancies
	 * @param $result
	 * @param $expectedPerms
	 * @return array
	 */
	private function validateObjectPermissions($result, $expectedPerms)
	{
		// create an array for the results
		$resultArray = null;
		$allPermissions= array('PermissionsCreate', 'PermissionsRead', 'PermissionsEdit', 'PermissionsDelete', 'PermissionsViewAllRecords', 'PermissionsModifyAllRecords');

		// Iterate through all of the expected perms and compare what is in the api results
		foreach ($result->records as $record) {
			// Was the object we're checking in the API results?
			if (array_key_exists($record->SobjectType, $expectedPerms)) {
				// Iterate through all possible sObject permissions
				foreach ($allPermissions as $permission) {
					// Empty is false. False is false.
					if ($record->$permission === true) {
						if (empty($expectedPerms[$record->SobjectType][$permission]) || $expectedPerms[$record->SobjectType][$permission] === false) {
							$resultArray[$record->SobjectType] = $permission;
						}
					}
				}
			}
		}
		return $resultArray;
	}

	/**
	 * Get the Integration User Rest API connection for a given account
	 * ** Note: $useApiVersion and $useRootUriPath are a pair that is used to get the global describe ONLY **
	 * @param piAccount $account
	 * @param bool $useApiVersion
	 * @param bool $useRootUriPath
	 * @return SalesforceRestApiWithJwtHelperWithoutConnector
	 * @throws Exception
	 */
	private function getIntegrationUserConnection($account, $useApiVersion = true, $useRootUriPath = false)
	{
		$salesforceOrgId = $this->getSalesforceOrgId($account);
		$instanceUrl = $this->getInstanceUrl($account);

		if (!$salesforceOrgId || !$instanceUrl) {
			PardotLogger::getInstance()->error("Attempted to get a connection but failed. Missing Org ID or Instance URL for account " . $account->id);
			return null;
		}

		$apiVersion =  $useApiVersion ? SalesforceRestApiAbstract::V_46 : '';
		$uriPath = $useRootUriPath ? '/services/data' : '/services/data/';

		$this->salesforceRestApi[$salesforceOrgId] = new SalesforceRestApiWithJwtHelperWithoutConnector($account->id, $salesforceOrgId,null,null, $instanceUrl, $apiVersion, $uriPath);

		return $this->salesforceRestApi[$salesforceOrgId];
	}

	/**
	 * Get the org id for an account from the Global tables -> 'global_account + sfdc_org '
	 * @param $account piAccount
	 * @return string|null
	 */
	protected function getSalesforceOrgId($account)
	{
		if (!$account) {
			PardotLogger::getInstance()->error("Unable to get the Salesforce Org Id without a valid piAccount");
			return null;
		}

		$salesforceOrg = piGlobalAccountTable::getInstance()->getSfdcOrgForAccountId($account->id);

		if (empty($salesforceOrg)) {
			PardotLogger::getInstance()->error("No valid Salesforce Org found for Account " . $account->id);
			return null;
		} else {
			return $salesforceOrg->fid;
		}
	}

	/**
	 * Retrieves the instance URL from the salesforce_api_environment Account Setting
	 * @param $account piAccount
	 * @return string
	 */
	protected function getInstanceUrl($account) {
		if (!$account) {
			PardotLogger::getInstance()->error("Unable to get the instance URL without a valid piAccount");
			return null;
		}

		$instanceUrl = AccountSettingsManager::getInstance($account->id)->getValue(AccountSettingsConstants::SALESFORCE_API_ENVIRONMENT);

		if (!$instanceUrl) {
			PardotLogger::getInstance()->error("Instance URL is not defined the salesforce_api_environment Account Setting");
			return null;
		}
		return $instanceUrl;
	}

	/**
	 * Runs a SOQL query as the integration user
	 * @param $query
	 * @return array|stdClass
	 * @throws \Pardot\Salesforce\RestApiException
	 */
	private function runSoqlQueryAsIntegrationUser($query) {
		$client = $this->getIntegrationUserConnection($this->account, true);
		return $client->runSoql($query);
	}

	/**
	 * Calls the /services/data endpoint to get a list of all API Versions. Since they are returned chronologically
	 * we can take the final value
	 * @return array|stdClass
	 * @throws \Pardot\Salesforce\RestApiException
	 */
	private function getSalesforceApiVersion() {
		$client = $this->getIntegrationUserConnection($this->account, false, true);

		$allVersions = $client->get('', [], true);
		$apiVersion = end($allVersions)['version'];
		reset($allVersions);

		return $apiVersion;
	}

}
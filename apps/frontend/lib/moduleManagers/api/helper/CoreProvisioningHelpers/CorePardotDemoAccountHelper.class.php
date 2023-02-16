<?php

class CorePardotDemoAccountHelper extends CoreProvisioningHelperBase {
	const GRAPHITE_KEY_PROVISIONING_DEMO_UNPROVISIONED_AVAILABILITY_ERROR = 'Provisioning.Demo.Unprovisioned.Availability.Error';
	const GRAPHITE_KEY_PROVISIONING_DEMO_CONFIGURATION_ACCOUNT_POOL_ERROR = 'Provisioning.Demo.Configuration.AccountPool.Error';

	/**
	 * @param $payload
	 * @return bool|void
	 * @throws sfStopException
	 */
	public function createPardotDemoAccount($payload) {
		//try to get unprovisioned account
		$accountData = $this->getAccountData($payload);

		//we required unprovisioned orgs to provision an account return error if there never will be any
		if (!$this->snapshotHasAccountPool($accountData['dataset'])) {
			return;
		}

		//provision account
		ShardManager::getInstance()->connectToShardDbByAccountId(AccountDropManager::PARDOT_ACCOUNT_ID);
		$unprovisionedDemoHandler = new UnprovisionedDemoHandler();
		$accountId = $unprovisionedDemoHandler->provisionDemoAccountCore($accountData);
		if (!is_null($accountId) && $accountId != false) {
			return $accountId;
		}

		GraphiteClient::increment(self::GRAPHITE_KEY_PROVISIONING_DEMO_UNPROVISIONED_AVAILABILITY_ERROR);
		PardotLogger::getInstance()->info("Core Provisioning: no unprovisioned demo orgs available for dataset {$accountData['dataset']}");
		$this->returnJsonError("no unprovisioned demo orgs available for dataset {$accountData['dataset']}", ProvisioningStatusCodes::UNKNOWN_ERROR);
	}

	/**
	 * @param string $accountName
	 * @param string $expirationDate
	 * @param string $timeZone
	 * @param string $dataset
	 * @param string $firstName
	 * @param string $lastName
	 * @param string $email
	 * @param string $password
	 * @return array
	 * @throws EncryptionException
	 */
	protected function getAccountData($payload)
	{
		$accountData = array(
			'dataset' => $this->getAndValidateDatasetId($payload['pardot_template_id']),
			'groupName' => "Pardot Sales",
			'accountNamePrefix' => "Demo - ",
			'userNamePrefix' => '',
			'numberOfAccounts' => 1,
			'expirationDate' => $this->getExpirationDate(),
			'dateAdjustment' => date("Y-m-d"),
			'isSalesGroup' => true,
			'accountName' => $payload['company'],
			'email' => $payload['email'],
			'userEmail' => '', // We don't have a global user because it's created via the API
			'firstName' => $payload['first_name'],
			'lastName' => $payload['last_name'],
			'timeZone' => $payload['timezone'],
			'orgId' => $payload['org_id'],
			'bpoId' => $payload['bpo_id'],
			'timestamp' => $payload['timestamp'],
		);

		return $accountData;
	}

	/**
	 * @return string
	 */
	protected function getExpirationDate()
	{
		$today= date("Y-m-d");
		$today_plus_240 = date("Y-m-d H:i:s", strtotime("$today +240 days"));
		return $today_plus_240;
	}

	/**
	 * Make sure the dataset is valid, if the id is invalid then use default dataset
	 *
	 * @param string $datasetId
	 *
	 * @return int
	 */
	public function getAndValidateDatasetId($datasetId) {
		if (is_null($datasetId) || !$this->isDatasetIdValid($datasetId)) {
			return DemoDataSet::getPrimarySnapshotId();
		}
		return $datasetId;
	}

	/**
	 * make sure dataset exists
	 *
	 * @param string $datasetId
	 * @return bool
	 */
	protected function isDatasetIdValid($datasetId) {
		$snapshot = piSnapshotTable::getInstance()->retrieveById($datasetId);
		return $snapshot != null;
	}

	/**
	 * Make sure that the dataset has unprovisioned orgs, if there are none we can't make an account
	 *
	 * @param string $datasetId
	 * @return bool
	 * @throws sfStopException
	 */
	protected function snapshotHasAccountPool($snapshotId) {
		$snapshots = piPublishedSnapshotTable::getInstance()->retrieveAllBySnapshotId($snapshotId);
		foreach($snapshots as $snapshot) {
			if($snapshot && $snapshot->desired_unprovisioned_orgs > 0) {
				return true;
			}
		}
		GraphiteClient::increment(self::GRAPHITE_KEY_PROVISIONING_DEMO_CONFIGURATION_ACCOUNT_POOL_ERROR);
		$this->returnJsonError("All Published Snapshots with Snapshot id: {$snapshotId} has desired unprovisioned orgs set to 0, needs to be at least 1", ProvisioningStatusCodes::UNKNOWN_ERROR);
		return false;
	}
}

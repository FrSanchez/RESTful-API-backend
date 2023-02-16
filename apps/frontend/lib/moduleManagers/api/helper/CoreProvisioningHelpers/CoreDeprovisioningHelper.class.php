<?php

class CoreDeprovisioningHelper extends CoreProvisioningHelperBase {

	/**
	 * @param bool $isDeletedRequest
	 * @param int $accountId
	 * @param string $orgId
	 * @param piSfdcPardotTenant $sfdcPardotTenant
	 * @param array $transformedPayload
	 * @return bool
	 * @throws sfStopException
	 */
	public function deprovisionOrArchiveAccountIfNeeded($isDeletedRequest, $accountId, $orgId, $sfdcPardotTenant, $transformedPayload) {
		if ($this->archivePardotAccountOnDeleteRequest($isDeletedRequest, $accountId, $orgId, $sfdcPardotTenant)) {
			$this->returnJsonSuccess($accountId, CoreProvisioningHelper::STATUS_DELETED);
			return true;
		} elseif ($this->deprovisionPardotAccountIfCriteriaAreMet($transformedPayload, $accountId, $orgId, $sfdcPardotTenant)) {
			$this->returnJsonSuccess($accountId, CoreProvisioningHelper::STATUS_DEPROVISIONED);
			return true;
		}
		return false;
	}

	/**
	 * Returns true if the Pardot account should be deprovisioned
	 *
	 * @param $transformedPayload
	 * @return bool
	 */
	public function shouldBeDeprovisioned($transformedPayload)
	{
		$orgIsLockedOrHold = in_array($transformedPayload["orgStatus"], ["LOCK", "HOLD"]);
		return ($orgIsLockedOrHold || $this->valueIsFalseOr0($transformedPayload['pardot']) ||
			$this->valueIsTrueOr1(!$this->pardotSkuOn($transformedPayload)));
	}

	/**
	 * Returns true if the Pardot account should be deleted
	 *
	 * @param $transformedPayload
	 * @return bool
	 */
	public function shouldBeDeleted($transformedPayload)
	{
		return $this->valueIsTrueOr1($transformedPayload['isDeleted']);
	}

	/**
	 * @param array $transformedPayload
	 * @param int $accountId
	 * @param piSfdcPardotTenant $sfdcPardotTenant
	 * @param string $orgId
	 * @return bool
	 * @throws sfStopException
	 */
	public function deprovisionPardotAccountIfCriteriaAreMet($transformedPayload, $accountId, $orgId, $sfdcPardotTenant)
	{
		// If the tenant should be deleted, set archived_at to seven days in the future. A job will archive it.
		$accountArchiveManager = new AccountArchiveManager(AccountProvisioningManager::SALESFORCE_PROVISIONING_USER_ID, $accountId);
		if ($this->shouldBeDeprovisioned($transformedPayload)) {
			GraphiteClient::startTiming('provisioning.coreDeprovisioning');
			$accountArchiveManager->archiveAccountOnDate('+7 days');
			$sfdcPardotTenant->status = piSfdcPardotTenant::STATUS_DEPROVISIONED;
			$sfdcPardotTenant->save();
			PardotLogger::getInstance()->info("Deprovisioning Pardot Account with id: {$accountId} and with OrgId: {$orgId} will archive in 7 days");
			GraphiteClient::increment("accountDeprovisioning.v2");
			GraphiteClient::stopTiming('provisioning.coreDeprovisioning');
			return true;
		}
		return false;
	}

	/**
	 * @param $isDeletedRequest
	 * @param $accountId
	 * @param $sfdcPardotTenant
	 * @return bool
	 * @throws sfStopException
	 *
	 * this should only be used in sandbox deprovisioning, standard deprovisioning doesn't use the isDeleted flag
	 *
	 */
	public function archivePardotAccountOnDeleteRequest($isDeletedRequest, $accountId, $orgId, $sfdcPardotTenant){
		if($isDeletedRequest){
			GraphiteClient::startTiming('provisioning.coreDeletion');
			$accountArchiveManager = new AccountArchiveManager(AccountProvisioningManager::SALESFORCE_PROVISIONING_USER_ID, $accountId);
			$sfdcPardotTenant->status = piSfdcPardotTenant::STATUS_DELETED;
			$sfdcPardotTenant->save();
			PardotLogger::getInstance()->info("Archiving Pardot Account with Id: $accountId and with OrgId: $orgId due to a deletion request");
			$accountArchiveManager->archiveAccount($accountId);
			GraphiteClient::increment("accountDeletion.v2");
			GraphiteClient::stopTiming('provisioning.coreDeletion');
			return true;
		}
		return false;

	}
}

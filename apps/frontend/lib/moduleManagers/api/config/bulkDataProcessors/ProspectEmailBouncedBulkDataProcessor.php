<?php
namespace Api\Config\BulkDataProcessors;

use AccountSettingsConstants;
use AccountSettingsManager;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use BinaryTools;
use Doctrine_Query;
use Pardot\Mailability\MailabilityStatus;

class ProspectEmailBouncedBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	const MAILABILITY_BITMAP = "mailability_bitmap";

	protected array $supportedFields = [
		"is_email_hard_bounced", "isEmailHardBounced",
		"email_bounced_at", "emailBouncedAt",
		"email_bounced_reason", "emailBouncedReason",
	];

	protected function getDefaultBulkQueryBatchSizeLimit() : int
	{
		return 2000;
	}

	protected function addFieldSelectionToPrimaryQuery(QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(self::MAILABILITY_BITMAP);
	}

	protected function addRecordToLoadIfNeedsLoading(int $recordId, ImmutableDoctrineRecord $doctrineRecord, $value = true) : bool
	{
		return parent::addRecordToLoadIfNeedsLoading(
			$recordId,
			$doctrineRecord,
			$doctrineRecord->get(self::MAILABILITY_BITMAP) // sets value of prospectIdsToLoad items to mailability bitmap
		);
	}

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$separator = ", ";
		$isEmailBouncedAtSelected = false;
		$isEmailBouncedReasonSelected = false;
		$query = Doctrine_Query::create();
		$accountId = $queryContext->getAccountId();
		$accountSettingsManager = AccountSettingsManager::getInstance($accountId);
		$isDecoupleDne = $accountSettingsManager->isFlagEnabled(AccountSettingsConstants::FEATURE_DECOUPLE_DNE);
		$select = "p.id";
		if ($this->isFieldSelected("emailBouncedAt") || $this->isFieldSelected("email_bounced_at")) {
			$select .= $separator . "eb.updated_at AS bouncedAt";
			$isEmailBouncedAtSelected = true;
		}
		if ($this->isFieldSelected("emailBouncedReason") || $this->isFieldSelected("email_bounced_reason")) {
			$select .= $separator . "eb.reason AS reason";
			$isEmailBouncedReasonSelected = true;
		}
		$firstProspectIdToLoad = array_key_first($prospectIdsToLoad);
		$hasMailabilityBitmap = !is_null($firstProspectIdToLoad) && is_numeric($prospectIdsToLoad[$firstProspectIdToLoad]);
		if ($hasMailabilityBitmap && $isDecoupleDne && !$isEmailBouncedAtSelected && !$isEmailBouncedReasonSelected) {
			// extract hard bounced state from mailability bitmap value in prospectIdsToLoad array
			foreach ($prospectIdsToLoad as $prospectId => $mailabilityBitmap) {
				if (BinaryTools::areAnyBitsSet((int)$mailabilityBitmap, [MailabilityStatus::IS_HARD_BOUNCED])) {
					$fieldValues = [];
					$fieldValues["isEmailHardBounced"] = true;
					$this->fetchedData[$prospectId] = $fieldValues;
				}
			}
		} else {
			// favor using mailability bitmap for checking hard bounced state when isDecoupleDne is enabled
			if ($hasMailabilityBitmap && $isDecoupleDne) {
				foreach ($prospectIdsToLoad as $prospectId => $mailabilityBitmap) {
					if (!BinaryTools::areAnyBitsSet((int)$mailabilityBitmap, [MailabilityStatus::IS_HARD_BOUNCED])) {
						unset($prospectIdsToLoad[$prospectId]);
					}
				}
			}
			if ($prospectIdsToLoad) {
				$query->select($select)
					->from('piProspect p')
					->innerJoin('p.piEmailBounce eb ON (p.email = eb.email_address AND eb.type = 3 AND eb.account_id = ?)', $accountId)
					->leftJoin('p.piEmailBounce eb2 ON (p.email = eb2.email_address AND eb2.type = 3 AND eb2.account_id = ? AND (eb.updated_at < eb2.updated_at))', $accountId)
					->where('p.account_id = ?', [$accountId])
					->andWhere('eb2.id IS NULL')
					->andWhereIn('p.id', array_keys($prospectIdsToLoad));
				if ($allowReadReplica) {
					$query->readReplicaSafe();
				}
				$queryResults = $query->executeAndFree();

				foreach ($queryResults as $queryResult) {
					$fieldValues = [];
					$fieldValues["isEmailHardBounced"] = true;
					if ($isEmailBouncedAtSelected) {
						$fieldValues["emailBouncedAt"] = $queryResult["bouncedAt"] ?? null;
					}
					if ($isEmailBouncedReasonSelected) {
						$fieldValues["emailBouncedReason"] = $queryResult["reason"] ?? null;
					}
					$this->fetchedData[$queryResult['id']] = $fieldValues;
				}
			}
		}
		// Don't free $queryResults doctrine collection here because it will invalidate piProspect Doctrine records
		// passed as input to modifyRecord calls resulting in invalid dereference that throws exception.
	}

	protected function getDbValue(int $recordId, FieldDefinition $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$arrayOfDbValues = $this->fetchedData[$recordId] ?? [];
		if (count($arrayOfDbValues) == 0) {
			return null;
		}

		switch ($selection->getName()) {
			case 'is_email_hard_bounced':
			case 'isEmailHardBounced':
				return $arrayOfDbValues['isEmailHardBounced'];
			case 'email_bounced_at':
			case 'emailBouncedAt':
				return $arrayOfDbValues['emailBouncedAt'];
			case 'email_bounced_reason':
			case 'emailBouncedReason':
				return $arrayOfDbValues['emailBouncedReason'];
			default:
				return null;
		}
	}
}

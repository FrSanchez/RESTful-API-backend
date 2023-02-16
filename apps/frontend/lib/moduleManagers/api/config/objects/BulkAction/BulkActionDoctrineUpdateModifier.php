<?php
namespace Api\Config\Objects\BulkAction;

use BulkActionApiSaveManager;
use Exception;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use piApiBulkAction;

class BulkActionDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @param DoctrineUpdateContext $updateContext
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var piApiBulkAction $bulkActionApi */
		$bulkActionApi = $updateContext->getDoctrineRecord();
		$accountId = $updateContext->getAccountId();
		$user = $updateContext->getUser();

		$bulkActionApiSaveManager = new BulkActionApiSaveManager($accountId, $user);

		$bulkActionApiSaveManager->validateUpdate($bulkActionApi, $updateContext->getRepresentation());
		$bulkActionApiSaveManager->performUpdate($bulkActionApi, $updateContext->getRepresentation());
	}

}

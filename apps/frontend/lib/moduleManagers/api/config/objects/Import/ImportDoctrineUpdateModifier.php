<?php
namespace Api\Config\Objects\Import;

use Exception;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use piBackgroundQueueTable;
use piImport;
use piImportFileTable;

class ImportDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	private ImportSaveManager $importSaveManager;

	public function __construct()
	{
		$this->importSaveManager = new ImportSaveManager();
	}

	/**
	 * @param DoctrineUpdateContext $updateContext
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var piImport $import */
		$import = $updateContext->getDoctrineRecord();
		$accountId = $updateContext->getAccountId();
		$user = $updateContext->getUser();

		$this->importSaveManager->validateUpdateWithRepresentation($import, $accountId, $updateContext->getRepresentation());
		$this->importSaveManager->doUpdate($import, $user);
	}

}

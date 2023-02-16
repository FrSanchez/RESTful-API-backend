<?php

namespace Api\Config\Objects\Import;

use Api\Gen\Representations\ImportRepresentation;
use Exception;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use GraphiteClient;
use Pardot\Constants\ShardDb\Import\StatusConstants;

class ImportDoctrineCreateModifier implements DoctrineCreateModifier
{
	private ImportSaveManager $importSaveManager;

	public function __construct()
	{
		$this->importSaveManager = new ImportSaveManager();
	}

	/**
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$importDoCreateStat = 'api.request.import.create.' . strtolower($createContext->getUser()->getRoleName());
		GraphiteClient::increment($importDoCreateStat, 0.05);

		/** @var ImportRepresentation $representation */
		$representation = $createContext->getRepresentation();

		$this->importSaveManager->validateDailyImportBatchCount($createContext->getUser(), $createContext->getApiActions()->isInternalRequest());

		$state = null;
		$columnOptions = null;
		$restoreDeleted = $representation->getRestoreDeleted();
		$createOnNoMatch = $representation->getCreateOnNoMatch();
		if ($representation->getIsStatusSet()) {
			$state = $this->importSaveManager->validateCreateState($representation->getStatus());
		}
		if ($representation->getIsFieldsSet()) {
			$columnOptions = $this->importSaveManager->sanitizeFieldOptions($representation->getFields(), $createContext->getAccountId(), $createContext->getVersion());
		}

		if (!is_null($createContext->getFileInput()) || $state == StatusConstants::WAITING) {
			$this->importSaveManager->verifyInputFile(
				$createContext->getFileInput(),
				$columnOptions,
				$createContext->getAccountId(),
				$createContext->getApiActions(),
				$representation->getIsCreateOnNoMatchSet() ? $createOnNoMatch : false
			);
		}

		$import = $this->importSaveManager->doCreate($createContext->getUser(), $state, $columnOptions, $restoreDeleted, $createOnNoMatch, $createContext->getFileInput(), $createContext->getApiActions()->isInternalRequest(), $createContext->getApiActions()->version);
		return [SystemFieldNames::ID => $import->id];
	}
}

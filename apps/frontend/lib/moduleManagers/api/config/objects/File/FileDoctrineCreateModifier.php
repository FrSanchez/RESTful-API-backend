<?php
namespace Api\Config\Objects\File;

use Api\Exceptions\ApiException;
use Api\Framework\FileInput as ApiFileInput;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use Filex;
use FilexPeer;
use FolderErrors;
use FolderManager;
use generalTools;
use Pardot\CompletionActions\ApiCompletionActionSaveManager;
use Pardot\Error\FolderSaveError;
use Pardot\Error\SaveError;
use Pardot\File\FileInput;
use Api\Gen\Representations\FileRepresentation;
use CampaignActionManager;
use Exception;
use Pardot\File\FileSaveManager;
use PardotLogger;
use RESTClient;
use stringTools;
use piUser;

class FileDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$errors = [];
		$campaignActionManager = new CampaignActionManager($createContext->getAccountId());
		$fileSaveManager = new FileSaveManager($campaignActionManager, new ApiCompletionActionSaveManager());

		/** @var Filex $filex */
		$filex = null;
		$isNewFile = true;

		$completionActions = []; //W-7815693 not supported yet
		$folderManager = new FolderManager();
		$userId = $createContext->getUser()->getUserId();

		/** @var FileRepresentation $representation */
		$representation = $createContext->getRepresentation();
		$newFileInput = $this->createFileInputFromRepresentation($representation, $createContext->getFileInput(), $userId);

		$validateResult = $this->validateCreate($fileSaveManager, $createContext->getAccountId(), $createContext->getUser(), $newFileInput, $filex, $errors, $isNewFile);
		$this->processAndThrowErrors($validateResult, $errors);

		try {
			$fileSaveManager->executeUpdateOrCreate(
				$createContext->getAccountId(),
				$userId,
				$newFileInput,
				$filex,
				$isNewFile,
				[],
				$completionActions
			);

			$folderId = $newFileInput->folder_id; //Will be the user-specified value or the default.

			//Folder access rights have already been checked by FileSaveManager
			$folderManager->moveOrCreateFolderObject($createContext->getAccountId(), $filex, $folderId, $userId);
		} catch (\Doctrine_Exception | \PropelException $e){
			PardotLogger::getInstance()->warn("Error when saving file: " . $e->getMessage());
			throw new ApiException(\ApiErrorLibrary::API_ERROR_UNKNOWN, '', RESTClient::HTTP_INTERNAL_SERVER_ERROR);
		} finally {
			$newFileInput->close();
		}

		return [SystemFieldNames::ID => $filex->getId()];
	}

	private function createFileInputFromRepresentation(FileRepresentation $representation, ?ApiFileInput $apiFileInput, int $userId) : FileInput
	{
		$fileInput = new FileInput();
		$fileInput->updated_by = $userId;
		$fileInput->isUpdatedBySet = true;

		if ($representation) {
			if ($representation->getIsNameSet()) {
				// make sure that the name is not empty or null
				if (stringTools::isNullOrBlank($representation->getName())) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						'name. Expected name to not be empty or null.',
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				$trimName = trim($representation->getName());

				// make sure the string is <=255 characters, which is DB field length
				if (mb_strlen($trimName) > FileSaveManager::MAX_NAME_LENGTH) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						'name. Expected name to be less than or equal to ' . FileSaveManager::MAX_NAME_LENGTH . ' characters.',
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				$fileInput->name = $trimName;
				$fileInput->isNameSet = true;
			}
			if ($representation->getIsFolderIdSet()) {
				if (is_null($representation->getFolderId())) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						"folderId. Folder ID cannot be null.",
						RESTClient::HTTP_BAD_REQUEST);
				}

				$fileInput->folder_id = $representation->getFolderId();
			}
		}
		if ($apiFileInput) {
			$extension = generalTools::getFileExtension($apiFileInput->getName());
			$doNotIndex = in_array($extension, FilexPeer::getUntrackedExtensions());

			$fileInput->vanity_url = $representation->getVanityUrlPath();
			$fileInput->tracker_domain_id = $representation->getTrackerDomainId();
			$fileInput->campaign_id = $representation->getCampaignId();
			$fileInput->is_do_not_index = $doNotIndex;
			$fileInput->upload_file = $apiFileInput->toFileInputContent();
		}
		return $fileInput;
	}

	/**
	 * @param FileSaveManager $fileSaveManager
	 * @param int $accountId
	 * @param piUser $user
	 * @param FileInput $fileInput
	 * @param Filex|null $filex
	 * @param array $errors
	 * @param bool $isNewFile
	 * @return bool
	 * @throws Exception
	 */
	private function validateCreate(FileSaveManager $fileSaveManager, int $accountId, piUser $user, FileInput $fileInput, ?Filex &$filex, array &$errors, bool &$isNewFile) : bool
	{
		if ($fileSaveManager->validateCreateOrUpdate(
			$accountId,
			$user,
			$fileInput,
			[],
			null,
			$filex,
			$errors,
			$isNewFile
		)) {
			if (!$fileInput->folder_id) {
				$errors[] = new FolderSaveError('folder_id', FolderErrors::INVALID_ID);
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * @param bool $validationResult
	 * @param SaveError[] $errors
	 */
	private function processAndThrowErrors(bool $validationResult, array $errors) : void
	{
		if (!$validationResult){
			foreach ($errors as $error){
				throw $error->createApiException();
			}
		}
	}
}

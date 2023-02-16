<?php
namespace Api\Config\Objects\File;

use Api\Exceptions\ApiException;
use Api\Framework\FileInput as ApiFileInput;
use Api\Gen\Representations\FileRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use CampaignActionManager;
use Doctrine_Exception;
use Exception;
use FilexPeer;
use FolderManager;
use generalTools;
use Pardot\CompletionActions\ApiCompletionActionSaveManager;
use Pardot\Error\SaveError;
use Pardot\File\Exceptions\FolderNotAccessibleFileException;
use Pardot\File\FileInput;
use Pardot\File\FileSaveManager;
use PardotLogger;
use piFilex;
use Filex;
use PropelException;
use RESTClient;
use stringTools;

class FileDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var FileRepresentation $representation */
		$representation = $updateContext->getRepresentation();

		/** @var piFilex $piFilex */
		$piFilex = $updateContext->getDoctrineRecord();

		$userId = $updateContext->getUser()->getUserId();
		$userFileInput = $this->createFileInput($representation, $updateContext->getFileInput(), $piFilex, $userId);

		/** @var SaveError[] $errors */
		$errors = [];
		$campaignActionManager = new CampaignActionManager($updateContext->getAccountId());
		$fileSaveManager = $this->getFileSaveManager($campaignActionManager);

		/** @var Filex $filex */
		$filex = null;
		$isNewFile = false;

		$completionActions = []; //W-7815693 not supported yet
		$folderManager = new FolderManager();
		try {
			$validateResult = $fileSaveManager->validateCreateOrUpdate(
				$updateContext->getAccountId(),
				$updateContext->getUser(),
				$userFileInput,
				[],
				null,
				$filex,
				$errors,
				$isNewFile,
				$completionActions
			);

			if (!$validateResult) {
				foreach ($errors as $error) {
					throw $error->createApiException();
				}
			}

			$fileSaveManager->executeUpdateOrCreate(
				$updateContext->getAccountId(),
				$userId,
				$userFileInput,
				$filex,
				$isNewFile,
				[],
				$completionActions
			);

			$folderId = $userFileInput->folder_id; //Will be the user-specified value or the default.

			//Folder access rights have already been checked by FileSaveManager
			$folderManager->moveOrCreateFolderObject($updateContext->getAccountId(), $filex, $folderId, $userId);
		} catch (FolderNotAccessibleFileException $e) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FOLDER_ID, null, RESTClient::HTTP_BAD_REQUEST);
		} catch (Doctrine_Exception | PropelException $e) {
			PardotLogger::getInstance()->warn("Error when saving file: " . $e->getMessage());
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN, '', RESTClient::HTTP_INTERNAL_SERVER_ERROR);
		} finally {
			$userFileInput->close();
		}
	}

	private function createFileInput(?FileRepresentation $representation, ?ApiFileInput $apiFileInput, piFilex $doctrine_Record, int $userId) : FileInput
	{
		$result = new FileInput();
		$result->id = $doctrine_Record->id;
		$result->updated_by = $userId;
		$result->isUpdatedBySet = true;
		$result->tracker_domain_id = $doctrine_Record->tracker_domain_id;
		$result->campaign_id = $doctrine_Record->campaign_id;
		$result->vanity_url_id = $doctrine_Record->vanity_url_id;
		$result->vanity_url = $doctrine_Record->getVanityUrlPath();

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

				$result->name = $trimName;
				$result->isNameSet = true;
			}
			if ($representation->getIsFolderIdSet()) {
				if (is_null($representation->getFolderId())) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						"folderId. Folder ID cannot be null.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}
				$result->folder_id = $representation->getFolderId();
			}
			if ($representation->getIsVanityUrlPathSet()) {
				$result->vanity_url = $representation->getVanityUrlPath();
			}
			if ($representation->getIsTrackerDomainIdSet()) {
				$result->tracker_domain_id = $representation->getTrackerDomainId();
			}
			if ($representation->getIsCampaignIdSet()) {
				$result->campaign_id = $representation->getCampaignId();
			}
		}
		if ($apiFileInput) {
			$extension = generalTools::getFileExtension($apiFileInput->getName());
			$doNotIndex = in_array($extension, FilexPeer::getUntrackedExtensions());

			$result->is_do_not_index = $doNotIndex;

			$result->upload_file = $apiFileInput->toFileInputContent();
		}

		return $result;
	}

	/**
	 * "Sprouted" factory method so that the dependency can be stubbed out with a mock
	 * @param CampaignActionManager $campaignActionManager
	 * @return FileSaveManager
	 */
	protected function getFileSaveManager(CampaignActionManager $campaignActionManager): FileSaveManager
	{
		return new FileSaveManager($campaignActionManager, new ApiCompletionActionSaveManager());
	}
}

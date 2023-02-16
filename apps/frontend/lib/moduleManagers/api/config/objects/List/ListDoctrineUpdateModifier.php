<?php

namespace Api\Config\Objects\Listx;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ListRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Pardot\Listx\ListSaveManager;
use PardotLogger;
use piListx;
use FolderManagerException;
use RESTClient;
use stringTools;

class ListDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @inheritDoc
	 * @throws FolderManagerException
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$representation = $updateContext->getRepresentation();
		if (!($representation instanceof ListRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to ListDoctrineCreateModifier is not of ListRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		if ($representation->getIsNameSet() && stringTools::isNullOrBlank($representation->getName())){
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "Name can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}

		if ($representation->getIsNameSet() && mb_strlen(trim($representation->getName())) > ListSaveManager::MAX_NAME_LENGTH) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'Expected Name to be less than or equal to ' . ListSaveManager::MAX_NAME_LENGTH . ' characters.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		/** @var piListx $piList */
		$piListx = $updateContext->getDoctrineRecord();
		$listSaveManager = new ListSaveManager();
		$listSaveManager->validateUpdate($updateContext->getUser(), $representation, $piListx);
		$listSaveManager->executeUpdate($updateContext->getUser(), $piListx, $representation);
	}
}

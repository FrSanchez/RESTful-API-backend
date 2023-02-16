<?php

namespace Api\Config\Objects\Listx;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ListRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use PardotLogger;
use RESTClient;
use stringTools;
use Pardot\Listx\ListSaveManager;

class ListDoctrineCreateModifier implements DoctrineCreateModifier
{
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$representation = $createContext->getRepresentation();
		if (!($representation instanceof ListRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to ListDoctrineCreateModifier is not of ListRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		if (!$representation->getIsNameSet() || stringTools::isNullOrBlank($representation->getName())) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "Name can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}

		if (mb_strlen(trim($representation->getName())) > ListSaveManager::MAX_NAME_LENGTH) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'Expected Name to be less than or equal to ' . ListSaveManager::MAX_NAME_LENGTH . ' characters.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$listSaveManager = new ListSaveManager();
		$listSaveManager->validateCreate($createContext->getUser(), $representation);
		$result = $listSaveManager->executeCreate($createContext->getUser(), $representation);

		return [SystemFieldNames::ID => $result->id];
	}
}

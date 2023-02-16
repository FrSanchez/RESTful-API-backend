<?php
namespace Api\Config\Objects\CustomField;

use Api\Gen\Representations\CustomFieldRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Pardot\ProspectField\ProspectFieldCustomSaveManager;
use Exception;
use PardotLogger;
use Api\Exceptions\ApiException;
use ApiErrorLibrary;
use RESTClient;
use Api\Objects\SystemFieldNames;

class CustomFieldDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$representation = $createContext->getRepresentation();
		if (!($representation instanceof CustomFieldRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to CustomFieldDoctrineCreateModifier is not of CustomFieldRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		$prospectFieldCustomSaveManager = new ProspectFieldCustomSaveManager($createContext->getVersion());
		$prospectFieldCustomSaveManager->validateCreate(
			$createContext->getUser(),
			$representation
		);

		$customField = $prospectFieldCustomSaveManager->executeCreate($createContext->getUser(), $representation);
		return [SystemFieldNames::ID => $customField->id];
	}
}

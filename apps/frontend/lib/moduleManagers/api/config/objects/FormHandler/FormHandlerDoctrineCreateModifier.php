<?php
namespace Api\Config\Objects\FormHandler;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\FormHandlerRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use Exception;
use Pardot\FormHandler\FormHandlerSaveManager;
use PardotLogger;
use RESTClient;

class FormHandlerDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$user = $createContext->getUser();
		$representation = $createContext->getRepresentation();

		if (!($representation instanceof FormHandlerRepresentation)) {
			PardotLogger::getInstance()->error(("The requested object to FormHandlerDoctrineCreateModifier is not of FormHandlerRepresentation"));
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
				"Invalid input",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$saveManager = new FormHandlerSaveManager($createContext->getAccountId());

		// Validate creation for Form Handler
		$saveManager->validateCreate(
			$representation,
			$user,
		);

		// Create the form handler
		$piFormHandler = $saveManager->executeCreate(
			$representation,
			$user
		);

		return [SystemFieldNames::ID => $piFormHandler->id];
	}
}

<?php

namespace Api\Config\Objects\FormHandlerField;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\FormHandlerFieldRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use Pardot\FormHandler\FormHandlerFieldSaveManager;
use PardotLogger;
use RESTClient;

class FormHandlerFieldDoctrineCreateModifier implements DoctrineCreateModifier
{

	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$user = $createContext->getUser();
		$representation = $createContext->getRepresentation();

		if (!($representation instanceof FormHandlerFieldRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to FormHandlerFoe;dDoctrineCreateModifier is not of FormHandlerFieldRepresentation");
			throw new ApiException(
					ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
					"Invalid input",
					RESTClient::HTTP_BAD_REQUEST
			);
		}

		$formHandlerFieldManager = new FormHandlerFieldSaveManager();
		$formHandlerFieldManager->validateCreate($representation, $user);
		$piFormHandlerField = $formHandlerFieldManager->create($representation, $user);

		return [SystemFieldNames::ID => $piFormHandlerField->id];
	}
}

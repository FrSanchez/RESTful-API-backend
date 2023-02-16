<?php

namespace Api\Config\Objects\FormHandlerField;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\FormHandlerFieldRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Pardot\FormHandler\FormHandlerFieldSaveManager;
use PardotLogger;
use RESTClient;

class FormHandlerFieldDoctrineUpdateModifier implements DoctrineUpdateModifier
{

	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$user = $updateContext->getUser();
		$representation = $updateContext->getRepresentation();

		$piFormHandlerFormField = $updateContext->getDoctrineRecord();

		if (!($representation instanceof FormHandlerFieldRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to FormHandlerFieldDoctrineUpdateModifier is not of FormHandlerFieldRepresentation");
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
				"Invalid input",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$saveManager = new FormHandlerFieldSaveManager();
		$saveManager->validateUpdate($representation, $piFormHandlerFormField, $user);
		$saveManager->update($representation, $piFormHandlerFormField, $user);
	}
}

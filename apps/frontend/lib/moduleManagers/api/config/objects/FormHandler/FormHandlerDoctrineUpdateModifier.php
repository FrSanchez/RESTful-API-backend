<?php
namespace Api\Config\Objects\FormHandler;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\FormHandlerRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Exception;
use Pardot\FormHandler\FormHandlerSaveManager;
use PardotLogger;
use piFormHandler;
use RESTClient;

class FormHandlerDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$user = $updateContext->getUser();
		$representation = $updateContext->getRepresentation();

		/** @var piFormHandler $piFormHandler */
		$piFormHandler = $updateContext->getDoctrineRecord();

		if (!($representation instanceof FormHandlerRepresentation)) {
			PardotLogger::getInstance()->error(("The requested object to FormHandlerDoctrineUpdateModifier is not of FormHandlerRepresentation"));
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
				"Invalid input",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$saveManager = new FormHandlerSaveManager($updateContext->getAccountId());

		// Validate update for Form Handler
		$saveManager->validateUpdate(
			$representation,
			$user,
			$piFormHandler
		);

		// Update the form handler
		$saveManager->executeUpdate(
			$representation,
			$user,
			$piFormHandler
		);
	}
}

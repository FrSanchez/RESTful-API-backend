<?php
namespace Api\Config\Objects\CustomRedirect;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\CustomRedirectRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Exception;
use Pardot\CustomUrl\CustomUrlInput;
use Pardot\CustomUrl\CustomUrlSaveManager;
use Pardot\Error\SaveError;
use piCustomUrl;
use RESTClient;

class CustomRedirectDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var CustomRedirectRepresentation $representation */
		$representation = $updateContext->getRepresentation();

		/** @var piCustomUrl $piCustomUrl */
		$piCustomUrl = $updateContext->getDoctrineRecord();

		$errors = [];
		$userId = $updateContext->getUser()->getUserId();
		$saveManager = new CustomUrlSaveManager();

		$completionActions = []; //not supported yet

		$customUrlInput = CustomUrlInput::createFromRepresentation($representation, $userId);
		$customUrlInput->id = $piCustomUrl->id;
		$validateResult = $saveManager->validateCreateOrUpdate(
			$updateContext->getAccountId(),
			$updateContext->getUser(),
			$customUrlInput,
			null,
			null,
			$errors
		);
		$this->processAndThrowErrors($validateResult, $errors);

		try {
			$saveManager->executeCreateOrUpdate(
				$updateContext->getAccountId(),
				$userId,
				$customUrlInput,
				$piCustomUrl,
				false,
				$completionActions
			);
		} catch (Exception $e) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN, null, RESTClient::HTTP_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	* @param bool $validationResult
	* @param SaveError[] $errors
	*/
	private function processAndThrowErrors(bool $validationResult, array $errors) : void
	{
		if (!$validationResult) {
			foreach ($errors as $error) {
				throw $error->createApiException();
			}
		}
	}
}

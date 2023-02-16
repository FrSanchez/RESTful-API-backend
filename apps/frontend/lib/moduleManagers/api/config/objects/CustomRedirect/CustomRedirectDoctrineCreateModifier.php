<?php
namespace Api\Config\Objects\CustomRedirect;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\CustomRedirectRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use ApiErrorLibrary;
use Exception;
use Pardot\CustomUrl\CustomUrlInput;
use Pardot\CustomUrl\CustomUrlSaveManager;
use Pardot\Error\SaveError;
use piCustomUrl;
use RESTClient;

class CustomRedirectDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$errors = [];
		$saveManager = new CustomUrlSaveManager();
		/** @var piCustomUrl $piCustomUrl */
		$piCustomUrl = null;
		$userId = $createContext->getUser()->getUserId();

		$completionActions = []; //not supported yet

		/** @var CustomRedirectRepresentation $representation */
		$representation = $createContext->getRepresentation();
		$customUrlInput = $this->createCustomUrlInputFromRepresentation($representation, $userId);
		$validateResult = $saveManager->validateCreateOrUpdate(
			$createContext->getAccountId(),
			$createContext->getUser(),
			$customUrlInput,
			null,
			null,
			$errors
		);
		$this->processAndThrowErrors($validateResult, $errors);

		try {
			$saveManager->executeCreateOrUpdate(
				$createContext->getAccountId(),
				$userId,
				$customUrlInput,
				$piCustomUrl,
				true,
				$completionActions
			);
		} catch (Exception $e) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN, null, RESTClient::HTTP_INTERNAL_SERVER_ERROR);
		}

		return ['id' => $piCustomUrl->id];
	}

	private function createCustomUrlInputFromRepresentation(CustomRedirectRepresentation $representation, int $userId) : CustomUrlInput
	{
		return CustomUrlInput::createFromRepresentation($representation, $userId);
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

<?php
namespace Api\Config\Objects\BulkAction;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\BulkActionRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use BulkActionApiSaveManager;
use Exception;
use PardotLogger;
use RESTClient;

class BulkActionDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		/** @var BulkActionRepresentation $bulkActionRepresentation */
		$bulkActionRepresentation = $createContext->getRepresentation();
		if (!($bulkActionRepresentation instanceof BulkActionRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to BulkActionDoctrineCreateModifier is not of BulkActionRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		$batchActionManager = new BulkActionApiSaveManager($createContext->getAccountId(), $createContext->getUser());
		$ids = $batchActionManager->validateCreate($createContext->getFileInput(), $bulkActionRepresentation);
		$bulkAction = $batchActionManager->performCreate($createContext->getFileInput()->getName(), $ids, $bulkActionRepresentation);

		return [SystemFieldNames::ID => $bulkAction->id];
	}
}

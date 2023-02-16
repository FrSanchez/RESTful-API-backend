<?php

namespace Api\Config\Objects\Prospect;

use Api\Exceptions\ApiException;
use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Api\Objects\SystemColumnNames;
use ApiErrorLibrary;
use Exception;
use PardotLogger;
use Prospect;
use ProspectPeer;
use ProspectSaveManager;
use RESTClient;

class ProspectDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		$prospectId = $deleteContext->getDoctrineRecord()->get('id');
		$prospectSaveManager = new ProspectSaveManager($deleteContext->getAccountId(), $deleteContext->getApiActions(), null);
		$identifiers= [
			SystemColumnNames::ID => $prospectId
		];
		// not used in delete V5
		$prospect = $prospectSaveManager->validateDelete($deleteContext->getUser(), $identifiers);
		if (!$prospect) {
			// This should never happen.
			PardotLogger::getInstance()->error("Couldn't load prospect {$prospectId} using Propel but was loaded using Doctrine!");
			throw new ApiException(ApiErrorLibrary::API_ERROR_OBJECT_NOT_FOUND, "", RESTClient::HTTP_INTERNAL_SERVER_ERROR);
		}
		$prospectSaveManager->performDelete($prospect, $deleteContext->getUser());
	}

	public function allowFrameworkDelete(): bool
	{
		return false;
	}

	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		// intentionally left blank
	}
}

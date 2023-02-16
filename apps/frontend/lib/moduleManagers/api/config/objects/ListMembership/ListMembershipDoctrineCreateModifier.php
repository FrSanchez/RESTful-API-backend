<?php

namespace Api\Config\Objects\ListMembership;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ListMembershipRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use Exception;
use Pardot\ListMembership\ListMembershipSaveManager;
use PardotLogger;
use RESTClient;

class ListMembershipDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @param DoctrineCreateContext $createContext
	 * @return array
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$representation = $createContext->getRepresentation();

		if (!($representation instanceof ListMembershipRepresentation)) {
			PardotLogger::getInstance()->error(("The requested object to ListMembershipDoctrineCreateModifier is not of ListMembershipRepresentation"));
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
				"Invalid input",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$user = $createContext->getUser();
		$saveManager = new ListMembershipSaveManager($createContext->getAccountId(), $createContext->getApiActions());

		$saveManager->validateCreate($representation, $user);

		$listMembership = $saveManager->executeCreate($representation, $user);
		return [SystemFieldNames::ID => $listMembership->id];
	}
}

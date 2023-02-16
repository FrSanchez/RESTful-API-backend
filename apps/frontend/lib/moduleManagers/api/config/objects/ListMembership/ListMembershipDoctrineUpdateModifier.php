<?php

namespace Api\Config\Objects\ListMembership;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ListMembershipRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Exception;
use Pardot\ListMembership\ListMembershipSaveManager;
use PardotLogger;
use piListxProspect;
use RESTClient;

class ListMembershipDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @param DoctrineUpdateContext $updateContext
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$representation = $updateContext->getRepresentation();

		if (!($representation instanceof ListMembershipRepresentation)) {
			PardotLogger::getInstance()->error(("The requested object to ListMembershipDoctrineCreateModifier is not of ListMembershipRepresentation"));
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
				"Invalid input",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$user = $updateContext->getUser();

		/** @var piListxProspect $listMembership */
		$listMembership = $updateContext->getDoctrineRecord();

		$saveManager = new ListMembershipSaveManager($updateContext->getAccountId(), $updateContext->getApiActions());

		$saveManager->validateUpdate($representation, $listMembership, $user);
		$saveManager->executeUpdate($representation, $listMembership, $user);
	}
}

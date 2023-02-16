<?php

namespace Api\Config\Objects\Prospect;

use Api\Gen\Representations\ProspectRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ProspectSaveManager;
use Exception;

class ProspectDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$prospectSaveManager = new ProspectSaveManager(
			$createContext->getAccountId(),
			$createContext->getApiActions()
		);

		/** @var ProspectRepresentation $prospectRepresentation */
		$prospectRepresentation = $createContext->getRepresentation();

		$prospectSaveManager->validateCreate(
			$createContext->getUser(),
			$prospectRepresentation,
			false
		);

		// this function is already behind a transaction. @see ApiObjectsCreateHandler::doCreate
		$prospect = $prospectSaveManager->performCreate(
			$createContext->getUser(),
			$prospectRepresentation,
			[],
			[],
			false
		);

		return [SystemFieldNames::ID => $prospect->getId()];
	}
}

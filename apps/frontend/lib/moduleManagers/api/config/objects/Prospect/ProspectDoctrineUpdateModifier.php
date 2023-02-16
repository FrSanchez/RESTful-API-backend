<?php

namespace Api\Config\Objects\Prospect;

use Api\Gen\Representations\ProspectRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use Doctrine_Record_Exception;
use PropelException;
use ProspectSaveManager;
use ValidationException;
use piProspect;

class ProspectDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @throws Doctrine_Exception
	 * @throws Doctrine_Record_Exception
	 * @throws PropelException
	 * @throws ValidationException
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$prospectSaveManager = new ProspectSaveManager(
			$updateContext->getAccountId(),
			$updateContext->getApiActions()
		);

		/** @var ProspectRepresentation $prospectRepresentation */
		$prospectRepresentation = $updateContext->getRepresentation();

		/** @var piProspect $prospectDoctrineRecord */
		$prospectDoctrineRecord = $updateContext->getDoctrineRecord();
		$recordId = $prospectDoctrineRecord->id;

		$prospect = $prospectSaveManager->validateUpdate(
			$updateContext->getUser(),
			$prospectRepresentation,
			[SystemColumnNames::ID => $recordId]
		);

		// this function is already behind a transaction. @see ApiObjectsUpdateHandler::doUpdatePartial
		$prospectSaveManager->performUpdate(
			$updateContext->getUser(),
			$prospectRepresentation,
			$prospect,
			[],
			[],
			false
		);
	}
}

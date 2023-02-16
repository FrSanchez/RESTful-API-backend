<?php

namespace Api\Config\Objects\ListMembership;

use Api\Exceptions\ApiException;
use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use ApiErrorLibrary;
use Exception;
use Pardot\ListMembership\ListMembershipSaveManager;
use piListxProspect;
use piListxTable;
use RESTClient;

class ListMembershipDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	/**
	 * @param DoctrineDeleteContext $deleteContext
	 */
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var piListxProspect $listMembership */
		$listMembership = $deleteContext->getDoctrineRecord();
		ListMembershipSaveManager::validateDelete($listMembership, $deleteContext->getAccountId());
	}

	/**
	 * @param DoctrineDeleteContext $deleteContext
	 * @throws Exception
	 */
	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var piListxProspect $listMembership */
		$listMembership = $deleteContext->getDoctrineRecord();
		ListMembershipSaveManager::executePostDeleteActions(
			$listMembership,
			$deleteContext->getApiActions(),
			$deleteContext->getAccountId()
		);
	}
}

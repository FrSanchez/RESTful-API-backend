<?php
namespace Api\Config\Objects\CustomField;

use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Pardot\ProspectField\ProspectFieldCustomSaveManager;
use piProspectFieldCustom;

class CustomFieldDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var  piProspectFieldCustom $piProspectFieldCustom */
		$piProspectFieldCustom = $deleteContext->getDoctrineRecord();

		$prospectFieldCustomSaveManager = new ProspectFieldCustomSaveManager($deleteContext->getVersion());
		$prospectFieldCustomSaveManager->validateDelete($piProspectFieldCustom);
	}

	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		// intentionally left blank
	}
}

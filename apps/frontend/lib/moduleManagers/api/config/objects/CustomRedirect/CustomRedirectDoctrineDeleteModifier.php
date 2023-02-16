<?php
namespace Api\Config\Objects\CustomRedirect;

use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Pardot\CustomUrl\CustomUrlSaveManager;
use piCustomUrl;

class CustomRedirectDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	/**
	 * @inheritDoc
	 */
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		// intentionally left blank
	}

	/**
	 * @inheritDoc
	 */
	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var piCustomUrl $piCustomUrl */
		$piCustomUrl = $deleteContext->getDoctrineRecord();

		$saveManager = new CustomUrlSaveManager();
		$saveManager->executeDelete($piCustomUrl);
	}
}

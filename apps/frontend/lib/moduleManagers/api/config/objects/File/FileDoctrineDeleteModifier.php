<?php
namespace Api\Config\Objects\File;

use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use Doctrine_Record_Exception;
use FilexPeer;
use Pardot\File\FileSaveManager;
use PardotLogger;
use piFilex;
use PropelException;

class FileDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	/**
	 * @inheritDoc
	 */
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		// Intentionally left blank
	}

	/**
	 * @inheritDoc
	 * @throws PropelException
	 * @throws Doctrine_Record_Exception
	 */
	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var piFilex $piFilex */
		$piFilex = $deleteContext->getDoctrineRecord();

		$filex = FilexPeer::retrieveByIds($piFilex->id, $piFilex->account_id);
		if (!$filex) {
			// the records was found, but reaching here is probably already deleted, therefore ignoring any change
			PardotLogger::getInstance()->warning("Unexpected trying to delete fileId {$piFilex->id} for account ($piFilex->account_id} but file was not found");
			return;
		}

		// doctrine_record has already the calling user stored in updated_by
		FileSaveManager::archiveFilex($filex, $deleteContext->getUser()->id);
		$piFilex->refresh();
	}

	public function allowFrameworkDelete(): bool
	{
		return false;
	}
}

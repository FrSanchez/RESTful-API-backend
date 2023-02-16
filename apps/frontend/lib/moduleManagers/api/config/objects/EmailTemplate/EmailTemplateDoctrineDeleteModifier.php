<?php

namespace Api\Config\Objects\EmailTemplate;

use Api\Exceptions\ApiException;
use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use ApiErrorLibrary;
use PardotLogger;
use RESTClient;

class EmailTemplateDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		$saveManager = new EmailTemplateSaveManager();
		$templateId = $deleteContext->getDoctrineRecord()->get('id');

		$emailTemplate = $saveManager->validateDelete($templateId, $deleteContext->getAccountId());
		if ($emailTemplate) {
			$saveManager->executeDelete($emailTemplate, $deleteContext->getUser()->getUserId());
		} else {
			PardotLogger::getInstance()->error("EmailtEmplate {$templateId} failed validation but didn't throw an error ");
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_TEMPLATE, null, RESTClient::HTTP_NOT_FOUND);
		}
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

<?php

namespace Api\Config\Objects\FormHandlerField;

use Api\Exceptions\ApiException;
use Api\Objects\Doctrine\DoctrineDeleteContext;
use Api\Objects\Doctrine\DoctrineDeleteModifier;
use ApiErrorLibrary;
use FormHandlerFormFieldDeleteManager;
use RESTClient;

class FormHandlerFieldDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	/**
	 * Validates delete of a Form Handler Form Field
	 *
	 * Pardot App UI requires a form handler to have at least one Default email field.
	 *
	 * @param DoctrineDeleteContext $deleteContext
	 * @throws \Doctrine_Query_Exception
	 */
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		/** @var \piFormHandlerFormField $formHandlerFormField */
		$formHandlerFormField = $deleteContext->getDoctrineRecord();
		$deleteManager = new FormHandlerFormFieldDeleteManager();

		if (!$deleteManager->validateDeleteFormHandlerFormField($deleteContext->getAccountId(), $formHandlerFormField)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_PROSPECT_DEFAULT_FIELD_EMAIL, '', RESTClient::HTTP_BAD_REQUEST);
		}
	}

	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		// TODO: Implement postDelete() method.
	}
}

<?php
namespace Api\Config\Objects\LayoutTemplate;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\LayoutTemplateRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use ApiErrorLibrary;
use Exception;
use FolderErrors;
use FolderManagerException;
use Pardot\LayoutTemplate\LayoutTemplateInput;
use Pardot\LayoutTemplate\LayoutTemplateSaveManager;
use PardotLogger;
use piLayoutTemplate;
use RESTClient;

class LayoutTemplateDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		$representation = $updateContext->getRepresentation();
		if (!($representation instanceof LayoutTemplateRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to LayoutTemplateDoctrineUpdateModifier is not of LayoutTemplateRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		$layoutTemplate = $updateContext->getDoctrineRecord();
		if (!($layoutTemplate instanceof piLayoutTemplate)) {
			PardotLogger::getInstance()->error("The requested doctrineRecord to LayoutTemplateDoctrineUpdateModifier is not of piLayoutTemplate");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}
		if ($representation->getIsNameSet() && empty(trim($representation->getName()))) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "Name can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}
		if ($representation->getIsLayoutContentSet() && empty($representation->getLayoutContent())) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "LayoutContent can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}

		try {
			$accountId = $layoutTemplate->account_id;
			$saveManager = new LayoutTemplateSaveManager();
			$newData = $representation->getLayoutContent();
			$oldData = $saveManager->getRegionsWithExistingContentForApi($accountId, $layoutTemplate->id, $layoutTemplate->layout_content, $layoutTemplate->form_content);

			if (!$saveManager->validateRegions($accountId, $oldData, $newData, $error)) {
				$errorMessage = $error['region'] . ": " . $error['message'];
				throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_PROPERTY, $errorMessage, RESTClient::HTTP_BAD_REQUEST);
			}
			if (! $saveManager->validateUpdate($newData,
				$representation->getFormContent(),
				$layoutTemplate->id,
				$accountId, $error))
			{
				$this->processErrors($error);
			}
			$input = LayoutTemplateInput::createFromApiRepresentation($representation, $accountId);
			$saveManager->executeUpdateOrCreate($input, null, $representation->getFolderId(), $layoutTemplate, $updateContext->getUser());
		} catch(FolderManagerException $fme) {
			$httpCode = RESTClient::HTTP_BAD_REQUEST;
			if ($fme->getCode() == FolderErrors::ACCESS_DENIED)
			{
				$httpCode = RESTClient::HTTP_UNAUTHORIZED;
			}
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_PROPERTY, $fme->getMessage(), $httpCode);
		} catch (Exception $exception) {
			//log and throw
			PardotLogger::getInstance()->warn('Error when trying to save layoutTemplate: ' . $exception->getMessage(), ['accountId'=>$accountId, 'object'=>'layoutTemplate', 'action'=>'save', 'code'=>$exception->getCode()] );
			throw $exception;
		}
	}

	/**
	 * @param array|null $error
	 * @throws ApiException
	 */
	private function processErrors(?array $error): void
	{
		if (is_array($error)) {
			$message = $error['name'] . ': ' . $error['message'];
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_PROPERTY, $message, RESTClient::HTTP_BAD_REQUEST);
		}
	}
}

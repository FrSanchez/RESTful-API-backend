<?php
namespace Api\Config\Objects\LayoutTemplate;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\LayoutTemplateRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use apiTools;
use Exception;
use FolderErrors;
use FolderManagerException;
use generalTools;
use Pardot\LayoutTemplate\LayoutTemplateInput;
use Pardot\LayoutTemplate\LayoutTemplateSaveManager;
use PardotLogger;
use piLayoutTemplate;
use piLayoutTemplateTable;
use RESTClient;

final class LayoutTemplateDoctrineCreateModifier implements DoctrineCreateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$representation = $createContext->getRepresentation();
		if (!($representation instanceof LayoutTemplateRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to LayoutTemplateDoctrineCreateModifier is not of LayoutTemplateRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}
		if (!$representation->getIsNameSet() || empty(trim($representation->getName()))) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "Name can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}
		if (!$representation->getIsLayoutContentSet() || empty($representation->getLayoutContent())) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_FIELDS, "LayoutContent can't be empty", RESTClient::HTTP_BAD_REQUEST);
		}

		$userId = $createContext->getUser()->getUserId();
		try {
			$layoutTemplate = new piLayoutTemplate();
			$layoutTemplate->account_id = $createContext->getAccountId();
			$layoutTemplate->created_by = $userId;
			$layoutTemplate->updated_by = $userId;
			$table = piLayoutTemplateTable::getInstance();
			if (!$representation->getIsFormContentSet()) {
				$layoutTemplate->form_content = $table->getDefaultFormContent();
			}
			//Site search content is always the default.
			$layoutTemplate->site_search_content = $table->getDefaultSiteSearchContent();


			$saveManager = new LayoutTemplateSaveManager();
			$newData = $representation->getLayoutContent();

			// oldData is only used by update, therefore it is null during create
			if (!$saveManager->validateRegions($createContext->getAccountId(), null, $newData, $error)) {
				$errorMessage = $error['region'] . ": " . $error['message'];
				throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_PROPERTY, $errorMessage, RESTClient::HTTP_BAD_REQUEST);
			}
			$folderId = $representation->getIsFolderIdSet() ? $representation->getFolderId() : apiTools::getDefaultFolderId($createContext->getAccountId(), generalTools::LAYOUT_TEMPLATE);
			if (! $saveManager->validateCreateOrUpdate(
				$representation->getName(),
				$newData,
				$representation->getFormContent(),
				$layoutTemplate->id,
				$createContext->getAccountId(), $folderId, $error))
			{
				$this->processErrors($error);
			}
			$input = LayoutTemplateInput::createFromApiRepresentation($representation, $createContext->getAccountId());
			$saveManager->executeUpdateOrCreate($input, null, $folderId, $layoutTemplate, $createContext->getUser());
		} catch(FolderManagerException $fme) {
			$httpCode = RESTClient::HTTP_BAD_REQUEST;
			if ($fme->getCode() == FolderErrors::ACCESS_DENIED)
			{
				$httpCode = RESTClient::HTTP_UNAUTHORIZED;
			}
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_PROPERTY, $fme->getMessage(), $httpCode);
		} catch (Exception $exception) {
			//log and throw
			PardotLogger::getInstance()->warn('Error when trying to save layoutTemplate: ' . $exception->getMessage(), ['accountId'=>$createContext->getAccountId(), 'object'=>'layoutTemplate', 'action'=>'save', 'code'=>$exception->getCode()] );
			throw $exception;
		}

		return [SystemFieldNames::ID => $layoutTemplate->id];
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

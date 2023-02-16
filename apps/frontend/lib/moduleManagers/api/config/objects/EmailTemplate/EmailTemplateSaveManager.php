<?php

namespace Api\Config\Objects\EmailTemplate;

use AccountSettingsConstants;
use AccountSettingsManager;
use Api\Config\Objects\Email\EmailReplyToOptionTypeEnum;
use Api\Config\Objects\Email\EmailSendOptionTypeEnum;
use Api\Exceptions\ApiException;
use Api\Gen\Representations\EmailTemplateRepresentation;
use Api\Gen\Representations\ReplyToOptionRepresentation;
use Api\Gen\Representations\SendOptionRepresentation;
use Api\Objects\ObjectDefinition;
use ApiErrorLibrary;
use Dependency;
use EmailConstants;
use EmailMessagePeer;
use EmailTemplate;
use EmailTemplateDraftParameterValidator;
use EmailTemplatePeer;
use Exception;
use FolderManager;
use generalTools;
use Pardot\Error\FolderSaveError;
use Pardot\Error\TrackerDomainSaveError;
use piCampaignTable;
use piEmailTemplate;
use piEmailTemplateDraft;
use piTrackerDomainTable;
use piUser;
use piUserTable;
use RESTClient;
use stringTools;
use ValidationException;

class EmailTemplateSaveManager
{
	private piCampaignTable $piCampaignTable;
	private piTrackerDomainTable $piTrackerDomainTable;
	private piUserTable $piUserTable;

	public function __construct(
		?piCampaignTable $piCampaignTable = null,
		?piTrackerDomainTable $piTrackerDomainTable = null,
		?piUserTable $piUserTable = null
	)
	{
		$this->piCampaignTable = is_null($piCampaignTable) ? piCampaignTable::getInstance() : $piCampaignTable;
		$this->piTrackerDomainTable = is_null($piTrackerDomainTable) ? piTrackerDomainTable::getInstance() : $piTrackerDomainTable;
		$this->piUserTable = is_null($piUserTable) ? piUserTable::getInstance() : $piUserTable;
	}

	/**
	 * @param EmailTemplateRepresentation $representation
	 * @param ObjectDefinition $definition
	 * @return array
	 */
	public function representationToDoctrineArray(EmailTemplateRepresentation $representation, ObjectDefinition $definition): array
	{
		$fields = $definition->getFields();

		// hydrate a doctrine keyed array of writable values
		$doctrineArray = [];
		foreach ($fields as $field) {
			if ($field->isReadOnly()) {
				continue;
			}

			$getter = 'get' . $field->getName();
			if (is_callable([$representation, $getter])) {
				$doctrineArray[$field->getDoctrineField()] = $representation->$getter();
			}

		}

		return $doctrineArray;
	}

	/**
	 * @param piUser $user
	 * @param piEmailTemplate $emailTemplate
	 * @param int|null $folderId
	 * @param bool $isNew
	 * @throws Exception
	 */
	public function saveTemplateToFolder(piUser $user, piEmailTemplate $emailTemplate, ?int $folderId, bool $isNew = true)
	{
		$folderManager = new FolderManager();

		if ($isNew) {
			if (!$folderId) {
				$defaultFolder = $folderManager->getDefaultFolderForType(
					$user->account_id,
					generalTools::EMAIL_TEMPLATE
				);
				$folderId = $defaultFolder ? $defaultFolder->id : null;
			}
		} else {
			$currentFolder = $folderManager->getFolderObjectByObjectTypeAndId(
				$user->account_id,
				generalTools::EMAIL_TEMPLATE,
				$emailTemplate->id
			);

			// return early if folder is already in its destination folder
			if ($currentFolder && $currentFolder->id === $folderId) {
				return;
			}
		}

		if ($folderId && !$folderManager->canAccessFolder($user->account_id, $folderId, $error, $user)) {
			$error = new FolderSaveError('folder_id', $error);
			throw $error->createApiException();
		}

		if ($folderId) {
			$folderManager->moveOrCreateFolderObject($user->account_id, $emailTemplate, $folderId, $user->getUserId());
		}
	}


	/**
	 * @param piEmailTemplateDraft $piEmailTemplateDraft
	 * @throws ApiException
	 */
	public function validateTemplateDraft(piEmailTemplateDraft $piEmailTemplateDraft): void
	{
		// validate type separately as some legacy enums are no longer allowed
		$enumTypes = (new EmailTemplateTypeEnum())->getArray();
		unset($enumTypes[EmailMessagePeer::INVALID]);
		unset($enumTypes[EmailMessagePeer::HTML_ONLY]);
		if (!in_array($piEmailTemplateDraft->type, array_keys($enumTypes))) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'type. Type must be one of: ' . implode(', ', $enumTypes) . '.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// ensure that one of the "available for" options is selected
		if (!$piEmailTemplateDraft->is_one_to_one_email &&
			!$piEmailTemplateDraft->is_list_email &&
			!$piEmailTemplateDraft->is_autoresponder_email &&
			!$piEmailTemplateDraft->is_drip_email) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_EMAIL_TEMPLATE_AVAILABLE_FOR,
				'one of isOneToOneEmail, isListEmail, isAutoresponderEmail or isDripEmail must be true.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$validator = new EmailTemplateDraftParameterValidator(
			$piEmailTemplateDraft->toArray(),
			null,
			null,
			$piEmailTemplateDraft->account_id
		);

		$options = [
			'is_being_scheduled' => false,
			'useHml' => !$piEmailTemplateDraft->isPmlAsset(),
		];

		$piCampaign = $this->piCampaignTable->retrieveByIds(
			$piEmailTemplateDraft->campaign_id,
			$piEmailTemplateDraft->account_id
		);
		if (!$piCampaign) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_CAMPAIGN_ID,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$this->validateTrackerDomainId($piEmailTemplateDraft->account_id, $piEmailTemplateDraft->tracker_domain_id);

		$validator->validate($options);

		if ($validator->hasErrors()) {
			$errorArray = $validator->getErrorStack()->toArray();

			// Validator is tightly coupled to the Flow UI for output. Do some cleanup here.
			$rawMessage = strip_tags(implode(PHP_EOL, current($errorArray)));
			// Remove duplicative field name if it exists
			$errorMessage = strpos($rawMessage, ':') ?
				substr($rawMessage, strpos($rawMessage, ':') + 1) : $rawMessage;

			$errorMessage = lcfirst(stringTools::camelize(key($errorArray))) . ": " . trim($errorMessage);

			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				strip_tags($errorMessage),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateTrackerDomainId(int $accountId, $trackerDomainId): void
	{
		if (is_null($trackerDomainId)) {
			return;
		}

		$trackerDomain = $this->piTrackerDomainTable->retrieveByIds($accountId, $trackerDomainId);
		if (empty($trackerDomain)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'trackerDomainId. Invalid tracker domain.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// Check for validation, giving primary domains a pass if they're serving
		if (!$trackerDomain->isValidated() && !$trackerDomain->is_primary) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				'trackerDomainId. Tracker domain has not been validated.',
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param int $templateId
	 * @param int $accountId
	 * @throws ApiException
	 */
	public function validateDelete(int $templateId, int $accountId): EmailTemplate
	{
		$defaultTemplateId = AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SETTING_ENGAGE_DEFAULT_TEMPLATE_ID);
		$isProspectResubscribeFFEnabled = AccountSettingsManager::getInstance($accountId)->isFlagEnabled(AccountSettingsConstants::FEATURE_HML_ENABLED_ACCOUNT);
		$resubscribeTemplateId = AccountSettingsManager::getInstance($accountId)->getValue(AccountSettingsConstants::SETTING_PROSPECT_RESUBSCRIBE_TEMPLATE_ID);

		if ($templateId == $defaultTemplateId) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_DEFAULT_TEMPLATE_DELETE, null, RESTClient::HTTP_BAD_REQUEST);
		}
		if ($isProspectResubscribeFFEnabled && $templateId == $resubscribeTemplateId) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_RESUBSCRIBE_TEMPLATE_DELETE, null, RESTClient::HTTP_BAD_REQUEST);
		}
		$record = EmailTemplatePeer::retrieveByIds($templateId, $accountId);
		if (!$record) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_TEMPLATE, null, RESTClient::HTTP_NOT_FOUND);
		}
		// this needs to be done before the call to validateRecord below to ensure
		// the additional dependency validation in the model (in addition to the manual ones) is run
		$record->setIsArchived(true);

		try {
			$fieldMap = array(EmailTemplatePeer::IS_ARCHIVED => 'is_archived');
			$record->validate();
		} catch (ValidationException $e) {
			$totalMessage = array();
			foreach ($e->getGeneralValidationFailedMessages() as $message) {
				$totalMessage[] = $message;
				$generalFormErrorMessage = implode(", ", $totalMessage);
				throw new ApiException(ApiErrorLibrary::API_ERROR_UNABLE_TO_DELETE, $generalFormErrorMessage, RESTClient::HTTP_BAD_REQUEST);
			}
			$columns = $e->getColumns();
			if (count($columns) > 0) {
				foreach ($columns as $column) {
					if (!array_key_exists($column, $fieldMap)) {
						throw new ApiException(ApiErrorLibrary::API_ERROR_UNABLE_TO_DELETE, null, RESTClient::HTTP_BAD_REQUEST);
					}
					$totalMessage = array();
					foreach ($e->getValidationFailedMessagesForColumn($column) as $message) {
						$totalMessage[] = $message;
						throw new ApiException(ApiErrorLibrary::API_ERROR_UNABLE_TO_DELETE, implode(", ", $totalMessage), RESTClient::HTTP_BAD_REQUEST);
					}
				}
			}
		}

		if (count(Dependency::getBusinessDependencies($record, $accountId))) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_ASSET_IN_USE, null, RESTClient::HTTP_BAD_REQUEST);
		}

		return $record;
	}

	/**
	 * @param SendOptionRepresentation[] $senderOptions
	 * @param int $accountId
	 */
	public function validateSenderOptions(array $senderOptions, int $accountId): void
	{
		if (empty($senderOptions)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				"senderOptions cannot be empty.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$enums = (new EmailSendOptionTypeEnum())->getArray();
		$optionTypes = [];
		$allowedEnums = [
			$enums[EmailConstants::SENDER_SPECIFIC_USER],
			$enums[EmailConstants::SENDER_GENERAL_USER],
			$enums[EmailConstants::SENDER_ASSIGNED_USER],
			$enums[EmailConstants::SENDER_ACCOUNT_OWNER],
		];
		foreach ($senderOptions as $senderOptionRepresentation) {
			if (!$senderOptionRepresentation->getIsTypeSet()) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
					"type. Type is required for Sender option.",
					RESTClient::HTTP_BAD_REQUEST
				);
			}

			$type = (int) $senderOptionRepresentation->getType();
			$optionTypes[] = $enums[$type];
			if ($type === EmailConstants::SENDER_GENERAL_USER) {
				if (!$senderOptionRepresentation->getIsAddressSet() || !$senderOptionRepresentation->getIsNameSet()) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
						"name, address. Sender option type '{$enums[$type]}' requires name and address to be set.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				// Make sure that the other properties specified are not set
				$this->validateAllowedSendOptionProperties(
					$senderOptionRepresentation,
					['type', 'address', 'name'],
					$enums[$type]
				);

			} elseif ($type === EmailConstants::SENDER_ACCOUNT_CUSTOM_FIELD || $type === EmailConstants::SENDER_PROSPECT_CUSTOM_FIELD) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
					"type. Sender option type '{$enums[$type]}' is not supported on create or update. Allowed types are: " . implode(',', $allowedEnums),
					RESTClient::HTTP_BAD_REQUEST
				);
			} elseif ($type === EmailConstants::SENDER_SPECIFIC_USER) {
				if (!$senderOptionRepresentation->getIsUserIdSet()) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
						"userId. Sender option type '{$enums[$type]}' requires userId to be set.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				// Make sure that the other properties specified are not set
				$this->validateAllowedSendOptionProperties(
					$senderOptionRepresentation,
					['type', 'userId'],
					$enums[$type]
				);

				// Validate user
				$userId = (int) $senderOptionRepresentation->getUserId();
				$piUser = $this->piUserTable->findOneByIdAndAccountId($userId, $accountId);
				if (!$piUser || $piUser->is_archived) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						"userId. User specified in Sender option type '{$enums[$type]}' either does not exist or is archived.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}
			} elseif ($type === EmailConstants::SENDER_ASSIGNED_USER || $type === EmailConstants::SENDER_ACCOUNT_OWNER) {
				$this->validateAllowedSendOptionProperties(
					$senderOptionRepresentation,
					['type'],
					$enums[$type]
				);
			}
		}

		if (count($optionTypes) > count(array_unique($optionTypes))) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				"senderOptions. Sender options cannot contain duplicate options types.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$lastOption = end($senderOptions);
		if (!in_array((int) $lastOption->getType(), [EmailConstants::SENDER_SPECIFIC_USER, EmailConstants::SENDER_GENERAL_USER])) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				"type. The last Sender option must be one of: {$enums[EmailConstants::SENDER_SPECIFIC_USER]}, {$enums[EmailConstants::SENDER_GENERAL_USER]}.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param ReplyToOptionRepresentation[] $replyToOptions
	 * @param int $accountId
	 */
	public function validateReplyToOptions(array $replyToOptions, int $accountId): void
	{
		if (empty($replyToOptions)) {
			return;
		}

		$enums = (new EmailReplyToOptionTypeEnum())->getArray();
		$optionTypes = [];
		$allowedEnums = [
			$enums[EmailConstants::REPLY_SPECIFIC_USER],
			$enums[EmailConstants::REPLY_GENERAL_ADDRESS],
			$enums[EmailConstants::REPLY_ASSIGNED_USER],
			$enums[EmailConstants::REPLY_ACCOUNT_OWNER],
		];
		foreach ($replyToOptions as $replyToOptionRepresentation) {
			if (!$replyToOptionRepresentation->getIsTypeSet()) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
					"type. Type is required within a Reply To option.",
					RESTClient::HTTP_BAD_REQUEST
				);
			}

			$type = (int) $replyToOptionRepresentation->getType();
			$optionTypes[] = $enums[$type];
			if ($type === EmailConstants::REPLY_GENERAL_ADDRESS) {
				if (!$replyToOptionRepresentation->getIsAddressSet()) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
						"address. Reply To option type '{$enums[$type]}' requires address to be set.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				// Make sure that the other properties specified are not set
				$this->validateAllowedReplyToOptionProperties(
					$replyToOptionRepresentation,
					['type', 'address'],
					$enums[$type]
				);

			} elseif ($type === EmailConstants::REPLY_ACCOUNT_CUSTOM_FIELD || $type === EmailConstants::REPLY_PROSPECT_CUSTOM_FIELD) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
					"type. Reply To option type '{$enums[$type]}' is not supported on create or update. Allowed types are: " . implode(',', $allowedEnums),
					RESTClient::HTTP_BAD_REQUEST
				);
			} elseif ($type === EmailConstants::REPLY_SPECIFIC_USER) {
				if (!$replyToOptionRepresentation->getIsUserIdSet()) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						"userId. Reply To option type '{$enums[$type]}' requires userId to be set.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}

				// Make sure that the other properties specified are not set
				$this->validateAllowedReplyToOptionProperties(
					$replyToOptionRepresentation,
					['type', 'userId'],
					$enums[$type]
				);

				// Validate user
				$userId = (int) $replyToOptionRepresentation->getUserId();
				$piUser = $this->piUserTable->findOneByIdAndAccountId($userId, $accountId);
				if (!$piUser || $piUser->is_archived) {
					throw new ApiException(
						ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
						"userId. User specified in Reply To option type '{$enums[$type]}' either does not exist or is archived.",
						RESTClient::HTTP_BAD_REQUEST
					);
				}
			} elseif ($type === EmailConstants::REPLY_ASSIGNED_USER || $type === EmailConstants::REPLY_ACCOUNT_OWNER) {
				// Make sure that the other properties specified are not set
				$this->validateAllowedReplyToOptionProperties(
					$replyToOptionRepresentation,
					['type'],
					$enums[$type]
				);
			}
		}

		if (count($optionTypes) > count(array_unique($optionTypes))) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				"replyToOptions. Reply To options cannot contain duplicate option types.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$lastOption = end($replyToOptions);
		if (!in_array((int) $lastOption->getType(), [EmailConstants::REPLY_SPECIFIC_USER, EmailConstants::REPLY_GENERAL_ADDRESS])) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				"type. The last Reply To option must be one of: {$enums[EmailConstants::REPLY_SPECIFIC_USER]}, {$enums[EmailConstants::REPLY_GENERAL_ADDRESS]}.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	public function executeDelete(EmailTemplate $record, int $deletedById): void
	{
		$record->setUpdatedBy($deletedById);
		$record->save();
	}

	private function validateAllowedReplyToOptionProperties(ReplyToOptionRepresentation $senderOptionRepresentation, array $allowedProperties, string $typeName): void
	{
		$invalidProperties = [];
		if ($senderOptionRepresentation->getIsAddressSet() &&
			!is_null($senderOptionRepresentation->getAddress()) &&
			!in_array('address', $allowedProperties)) {
			$invalidProperties[] = 'address';
		}
		if ($senderOptionRepresentation->getIsUserIdSet() &&
			!is_null($senderOptionRepresentation->getUserId()) &&
			!in_array('userId', $allowedProperties)) {
			$invalidProperties[] = 'userId';
		}
		if ($senderOptionRepresentation->getIsProspectCustomFieldIdSet() &&
			!is_null($senderOptionRepresentation->getProspectCustomFieldId()) &&
			!in_array('prospectCustomFieldId', $allowedProperties)) {
			$invalidProperties[] = 'prospectCustomFieldId';
		}
		if ($senderOptionRepresentation->getIsAccountCustomFieldIdSet() &&
			!is_null($senderOptionRepresentation->getAccountCustomFieldId()) &&
			!in_array('accountCustomFieldId', $allowedProperties)) {
			$invalidProperties[] = 'accountCustomFieldId';
		}
		if ($invalidProperties) {
			sort($invalidProperties);
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				join(', ', $invalidProperties) . ". These properties are not valid for Reply To option type '{$typeName}'.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateAllowedSendOptionProperties(SendOptionRepresentation $senderOptionRepresentation, array $allowedProperties, string $typeName): void
	{
		$invalidProperties = [];
		if ($senderOptionRepresentation->getIsAddressSet() &&
			!is_null($senderOptionRepresentation->getAddress()) &&
			!in_array('address', $allowedProperties)) {
			$invalidProperties[] = 'address';
		}
		if ($senderOptionRepresentation->getIsNameSet() &&
			!is_null($senderOptionRepresentation->getName()) &&
			!in_array('name', $allowedProperties)) {
			$invalidProperties[] = 'name';
		}
		if ($senderOptionRepresentation->getIsUserIdSet() &&
			!is_null($senderOptionRepresentation->getUserId()) &&
			!in_array('userId', $allowedProperties)) {
			$invalidProperties[] = 'userId';
		}
		if ($senderOptionRepresentation->getIsProspectCustomFieldIdSet() &&
			!is_null($senderOptionRepresentation->getProspectCustomFieldId()) &&
			!in_array('prospectCustomFieldId', $allowedProperties)) {
			$invalidProperties[] = 'prospectCustomFieldId';
		}
		if ($senderOptionRepresentation->getIsAccountCustomFieldIdSet() &&
			!is_null($senderOptionRepresentation->getAccountCustomFieldId()) &&
			!in_array('accountCustomFieldId', $allowedProperties)) {
			$invalidProperties[] = 'accountCustomFieldId';
		}
		if ($invalidProperties) {
			sort($invalidProperties);
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				join(', ', $invalidProperties) . ". These properties are not valid for Sender option type '{$typeName}'.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}
}

<?php
namespace Api\Config\Objects\EmailTemplate;

use Api\Gen\Representations\EmailTemplateRepresentation;
use Api\Objects\Doctrine\DoctrineCreateContext;
use Api\Objects\Doctrine\DoctrineCreateModifier;
use piEmailTemplateDraft;

class EmailTemplateDoctrineCreateModifier implements DoctrineCreateModifier
{

	/**
	 * @throws \Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array
	{
		$templateSaveManager = new EmailTemplateSaveManager();
		/** @var EmailTemplateRepresentation $templateRepresentation */
		$templateRepresentation = $createContext->getRepresentation();

		$doctrineArray = $templateSaveManager->representationToDoctrineArray(
			$templateRepresentation,
			$createContext->getObjectDefinition()
		);

		// Build a piEmailTemplateDraft object for validation
		$piEmailTemplateDraft = new piEmailTemplateDraft();
		$piEmailTemplateDraft->fromArray($doctrineArray);

		// Set standard fields
		$piEmailTemplateDraft->account_id = $createContext->getAccountId();
		$piEmailTemplateDraft->created_by = $createContext->getUser()->getUserId();
		$piEmailTemplateDraft->updated_by = $createContext->getUser()->getUserId();

		$templateSaveManager->validateSenderOptions($templateRepresentation->getSenderOptions(), $createContext->getAccountId());
		$piEmailTemplateDraft->send_from_data = SenderOptionsTransformer::
			convertSendOptionRepresentationsToDbValue($templateRepresentation->getSenderOptions());

		if ($templateRepresentation->getReplyToOptions()) {
			$templateSaveManager->validateReplyToOptions($templateRepresentation->getReplyToOptions(), $createContext->getAccountId());
			$piEmailTemplateDraft->reply_to_address = ReplyToOptionsTransformer::
				convertReplyToOptionsRepresentationToDbValue($templateRepresentation->getReplyToOptions());
		}

		// Validate EmailTemplateRepresentation by way of piEmailTemplateDraft
		$templateSaveManager->validateTemplateDraft($piEmailTemplateDraft);

		// Convert EmailTemplateDraft to a published EmailTemplate
		$piEmailTemplate = $piEmailTemplateDraft->toTemplate();
		$piEmailTemplate->save();

		$templateSaveManager->saveTemplateToFolder(
			$createContext->getUser(),
			$piEmailTemplate,
			$templateRepresentation->getFolderId()
		);

		return ['id' => $piEmailTemplate->id];
	}

}

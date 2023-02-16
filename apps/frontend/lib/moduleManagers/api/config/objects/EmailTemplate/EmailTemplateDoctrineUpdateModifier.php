<?php
namespace Api\Config\Objects\EmailTemplate;

use Api\Gen\Representations\EmailTemplateRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use piEmailTemplate;
use piEmailTemplateDraft;

class EmailTemplateDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var piEmailTemplate $doctrineRecord */
		$doctrineRecord = $updateContext->getDoctrineRecord();
		$templateSaveManager = new EmailTemplateSaveManager();
		/** @var EmailTemplateRepresentation $templateRepresentation */
		$templateRepresentation = $updateContext->getRepresentation();

		$piEmailTemplateDraft = new piEmailTemplateDraft();
		$draftArray = $templateSaveManager->representationToDoctrineArray(
			$templateRepresentation,
			$updateContext->getObjectDefinition()
		);

		// No fields are nullable, remove null representation fields
		$draftArray = array_filter($draftArray, function($value) {
			return !is_null($value);
		});
		$piEmailTemplateDraft->fromArray(array_merge($doctrineRecord->toArray(), $draftArray));

		if ($templateRepresentation->getSenderOptions()) {
			$templateSaveManager->validateSenderOptions(
				$templateRepresentation->getSenderOptions(),
				$updateContext->getAccountId()
			);
			$piEmailTemplateDraft->send_from_data = SenderOptionsTransformer::
				convertSendOptionRepresentationsToDbValue($templateRepresentation->getSenderOptions());
		}

		if ($templateRepresentation->getReplyToOptions()) {
			$templateSaveManager->validateReplyToOptions(
				$templateRepresentation->getReplyToOptions(),
				$updateContext->getAccountId()
			);
			$piEmailTemplateDraft->reply_to_address = ReplyToOptionsTransformer::
				convertReplyToOptionsRepresentationToDbValue($templateRepresentation->getReplyToOptions());
		}

		// Validate EmailTemplateRepresentation by way of piEmailTemplateDraft
		$templateSaveManager->validateTemplateDraft($piEmailTemplateDraft);

		$doctrineRecord->fromArray($draftArray);
		$doctrineRecord->updated_by = $updateContext->getUser()->getUserId();
		$piEmailTemplateDraft->toTemplate($doctrineRecord);
		$doctrineRecord->save();

		$templateSaveManager->saveTemplateToFolder(
			$updateContext->getUser(),
			$doctrineRecord,
			$templateRepresentation->getFolderId(),
			false
		);
	}

}

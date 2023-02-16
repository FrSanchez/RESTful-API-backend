<?php
namespace Api\Config\Objects\Email\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\EmailRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class EmailRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof EmailRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				EmailRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof EmailRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				EmailRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param EmailRepresentation $representation
	 */
	private function validateReadOnlyFields(EmailRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCampaignSet()) {
			$invalidFields[] = 'campaign';
		}

		if ($representation->getIsClientTypeSet()) {
			$invalidFields[] = 'clientType';
		}

		if ($representation->getIsCreatedBySet()) {
			$invalidFields[] = 'createdBy';
		}

		if ($representation->getIsCreatedByIdSet()) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsListSet()) {
			$invalidFields[] = 'list';
		}

		if ($representation->getIsListEmailSet()) {
			$invalidFields[] = 'listEmail';
		}

		if ($representation->getIsProspectSet()) {
			$invalidFields[] = 'prospect';
		}

		if ($representation->getIsSentAtSet()) {
			$invalidFields[] = 'sentAt';
		}

		if ($representation->getIsUserSet()) {
			$invalidFields[] = 'user';
		}

		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				implode(', ', $invalidFields) . ". These fields are read only.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param EmailRepresentation $representation
	 */
	private function validateRequiredFields(EmailRepresentation $representation): void
	{
		$invalidFields = [];


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateNonNullableFields(EmailRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCampaignIdSet() && is_null($representation->getCampaignId())) {
			$invalidFields[] = 'campaignId';
		}

		if ($representation->getIsClientTypeSet() && is_null($representation->getClientType())) {
			$invalidFields[] = 'clientType';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsHtmlMessageSet() && is_null($representation->getHtmlMessage())) {
			$invalidFields[] = 'htmlMessage';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsListEmailIdSet() && is_null($representation->getListEmailId())) {
			$invalidFields[] = 'listEmailId';
		}

		if ($representation->getIsListIdSet() && is_null($representation->getListId())) {
			$invalidFields[] = 'listId';
		}

		if ($representation->getIsNameSet() && is_null($representation->getName())) {
			$invalidFields[] = 'name';
		}

		if ($representation->getIsProspectIdSet() && is_null($representation->getProspectId())) {
			$invalidFields[] = 'prospectId';
		}

		if ($representation->getIsSentAtSet() && is_null($representation->getSentAt())) {
			$invalidFields[] = 'sentAt';
		}

		if ($representation->getIsSubjectSet() && is_null($representation->getSubject())) {
			$invalidFields[] = 'subject';
		}

		if ($representation->getIsTextMessageSet() && is_null($representation->getTextMessage())) {
			$invalidFields[] = 'textMessage';
		}

		if ($representation->getIsUserIdSet() && is_null($representation->getUserId())) {
			$invalidFields[] = 'userId';
		}


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				implode(', ', $invalidFields) . ". These fields should not be null.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}
}

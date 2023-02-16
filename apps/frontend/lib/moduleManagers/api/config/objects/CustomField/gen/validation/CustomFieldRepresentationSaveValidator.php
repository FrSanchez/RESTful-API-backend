<?php
namespace Api\Config\Objects\CustomField\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\CustomFieldRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class CustomFieldRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof CustomFieldRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				CustomFieldRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof CustomFieldRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				CustomFieldRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param CustomFieldRepresentation $representation
	 */
	private function validateReadOnlyFields(CustomFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsApiFieldIdSet()) {
			$invalidFields[] = 'apiFieldId';
		}

		if ($representation->getIsCreatedAtSet()) {
			$invalidFields[] = 'createdAt';
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

		if ($representation->getIsIsAnalyticsSyncedSet()) {
			$invalidFields[] = 'isAnalyticsSynced';
		}

		if ($representation->getIsUpdatedAtSet()) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedBySet()) {
			$invalidFields[] = 'updatedBy';
		}

		if ($representation->getIsUpdatedByIdSet()) {
			$invalidFields[] = 'updatedById';
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
	 * @param CustomFieldRepresentation $representation
	 */
	private function validateRequiredFields(CustomFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if (!$representation->getIsFieldIdSet()) {
			$invalidFields[] = 'fieldId';
		}

		if (!$representation->getIsNameSet()) {
			$invalidFields[] = 'name';
		}

		if (!$representation->getIsTypeSet()) {
			$invalidFields[] = 'type';
		}


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateNonNullableFields(CustomFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsApiFieldIdSet() && is_null($representation->getApiFieldId())) {
			$invalidFields[] = 'apiFieldId';
		}

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsFieldIdSet() && is_null($representation->getFieldId())) {
			$invalidFields[] = 'fieldId';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsAnalyticsSyncedSet() && is_null($representation->getIsAnalyticsSynced())) {
			$invalidFields[] = 'isAnalyticsSynced';
		}

		if ($representation->getIsIsRecordMultipleResponsesSet() && is_null($representation->getIsRecordMultipleResponses())) {
			$invalidFields[] = 'isRecordMultipleResponses';
		}

		if ($representation->getIsIsRequiredSet() && is_null($representation->getIsRequired())) {
			$invalidFields[] = 'isRequired';
		}

		if ($representation->getIsIsUseValuesSet() && is_null($representation->getIsUseValues())) {
			$invalidFields[] = 'isUseValues';
		}

		if ($representation->getIsNameSet() && is_null($representation->getName())) {
			$invalidFields[] = 'name';
		}

		if ($representation->getIsSalesforceIdSet() && is_null($representation->getSalesforceId())) {
			$invalidFields[] = 'salesforceId';
		}

		if ($representation->getIsTypeSet() && is_null($representation->getType())) {
			$invalidFields[] = 'type';
		}

		if ($representation->getIsUpdatedAtSet() && is_null($representation->getUpdatedAt())) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedByIdSet() && is_null($representation->getUpdatedById())) {
			$invalidFields[] = 'updatedById';
		}

		if ($representation->getIsValuesPrefillSet() && is_null($representation->getValuesPrefill())) {
			$invalidFields[] = 'valuesPrefill';
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

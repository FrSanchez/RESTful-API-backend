<?php
namespace Api\Config\Objects\FormHandlerField\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\FormHandlerFieldRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class FormHandlerFieldRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof FormHandlerFieldRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				FormHandlerFieldRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof FormHandlerFieldRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				FormHandlerFieldRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param FormHandlerFieldRepresentation $representation
	 */
	private function validateReadOnlyFields(FormHandlerFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCreatedAtSet()) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedBySet()) {
			$invalidFields[] = 'createdBy';
		}

		if ($representation->getIsCreatedByIdSet()) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsFormHandlerSet()) {
			$invalidFields[] = 'formHandler';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
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
	 * @param FormHandlerFieldRepresentation $representation
	 */
	private function validateRequiredFields(FormHandlerFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if (!$representation->getIsDataFormatSet()) {
			$invalidFields[] = 'dataFormat';
		}

		if (!$representation->getIsFormHandlerIdSet()) {
			$invalidFields[] = 'formHandlerId';
		}

		if (!$representation->getIsNameSet()) {
			$invalidFields[] = 'name';
		}

		if (!$representation->getIsProspectApiFieldIdSet()) {
			$invalidFields[] = 'prospectApiFieldId';
		}


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateNonNullableFields(FormHandlerFieldRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsDataFormatSet() && is_null($representation->getDataFormat())) {
			$invalidFields[] = 'dataFormat';
		}

		if ($representation->getIsErrorMessageSet() && is_null($representation->getErrorMessage())) {
			$invalidFields[] = 'errorMessage';
		}

		if ($representation->getIsFormHandlerIdSet() && is_null($representation->getFormHandlerId())) {
			$invalidFields[] = 'formHandlerId';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsMaintainInitialValueSet() && is_null($representation->getIsMaintainInitialValue())) {
			$invalidFields[] = 'isMaintainInitialValue';
		}

		if ($representation->getIsIsRequiredSet() && is_null($representation->getIsRequired())) {
			$invalidFields[] = 'isRequired';
		}

		if ($representation->getIsNameSet() && is_null($representation->getName())) {
			$invalidFields[] = 'name';
		}

		if ($representation->getIsProspectApiFieldIdSet() && is_null($representation->getProspectApiFieldId())) {
			$invalidFields[] = 'prospectApiFieldId';
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

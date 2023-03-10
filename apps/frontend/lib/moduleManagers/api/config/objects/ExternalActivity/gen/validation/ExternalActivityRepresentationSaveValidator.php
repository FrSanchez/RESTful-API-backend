<?php
namespace Api\Config\Objects\ExternalActivity\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ExternalActivityRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class ExternalActivityRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof ExternalActivityRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ExternalActivityRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof ExternalActivityRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ExternalActivityRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param ExternalActivityRepresentation $representation
	 */
	private function validateReadOnlyFields(ExternalActivityRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsActivityDateSet()) {
			$invalidFields[] = 'activityDate';
		}

		if ($representation->getIsCreatedAtSet()) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet()) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsExtensionSet()) {
			$invalidFields[] = 'extension';
		}

		if ($representation->getIsExtensionSalesforceIdSet()) {
			$invalidFields[] = 'extensionSalesforceId';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsProspectIdSet()) {
			$invalidFields[] = 'prospectId';
		}

		if ($representation->getIsTypeSet()) {
			$invalidFields[] = 'type';
		}

		if ($representation->getIsTypeSalesforceIdSet()) {
			$invalidFields[] = 'typeSalesforceId';
		}

		if ($representation->getIsValueSet()) {
			$invalidFields[] = 'value';
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
	 * @param ExternalActivityRepresentation $representation
	 */
	private function validateRequiredFields(ExternalActivityRepresentation $representation): void
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

	private function validateNonNullableFields(ExternalActivityRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsActivityDateSet() && is_null($representation->getActivityDate())) {
			$invalidFields[] = 'activityDate';
		}

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsExtensionSet() && is_null($representation->getExtension())) {
			$invalidFields[] = 'extension';
		}

		if ($representation->getIsExtensionSalesforceIdSet() && is_null($representation->getExtensionSalesforceId())) {
			$invalidFields[] = 'extensionSalesforceId';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsProspectIdSet() && is_null($representation->getProspectId())) {
			$invalidFields[] = 'prospectId';
		}

		if ($representation->getIsTypeSet() && is_null($representation->getType())) {
			$invalidFields[] = 'type';
		}

		if ($representation->getIsTypeSalesforceIdSet() && is_null($representation->getTypeSalesforceId())) {
			$invalidFields[] = 'typeSalesforceId';
		}

		if ($representation->getIsValueSet() && is_null($representation->getValue())) {
			$invalidFields[] = 'value';
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

<?php
namespace Api\Config\Objects\ProspectAccount\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ProspectAccountRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class ProspectAccountRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof ProspectAccountRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ProspectAccountRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof ProspectAccountRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ProspectAccountRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param ProspectAccountRepresentation $representation
	 */
	private function validateReadOnlyFields(ProspectAccountRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsAssignedToSet()) {
			$invalidFields[] = 'assignedTo';
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

		if ($representation->getIsIsDeletedSet()) {
			$invalidFields[] = 'isDeleted';
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
	 * @param ProspectAccountRepresentation $representation
	 */
	private function validateRequiredFields(ProspectAccountRepresentation $representation): void
	{
		$invalidFields = [];

		if (!$representation->getIsNameSet()) {
			$invalidFields[] = 'name';
		}


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateNonNullableFields(ProspectAccountRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsDeletedSet() && is_null($representation->getIsDeleted())) {
			$invalidFields[] = 'isDeleted';
		}

		if ($representation->getIsNameSet() && is_null($representation->getName())) {
			$invalidFields[] = 'name';
		}

		if ($representation->getIsSalesforceIdSet() && is_null($representation->getSalesforceId())) {
			$invalidFields[] = 'salesforceId';
		}

		if ($representation->getIsUpdatedAtSet() && is_null($representation->getUpdatedAt())) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedByIdSet() && is_null($representation->getUpdatedById())) {
			$invalidFields[] = 'updatedById';
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
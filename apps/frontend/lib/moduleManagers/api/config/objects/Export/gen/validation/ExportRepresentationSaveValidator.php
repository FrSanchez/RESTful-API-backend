<?php
namespace Api\Config\Objects\Export\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ExportRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class ExportRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof ExportRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ExportRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof ExportRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ExportRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param ExportRepresentation $representation
	 */
	private function validateReadOnlyFields(ExportRepresentation $representation): void
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

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsExpiredSet()) {
			$invalidFields[] = 'isExpired';
		}

		if ($representation->getIsResultRefsSet()) {
			$invalidFields[] = 'resultRefs';
		}

		if ($representation->getIsStatusSet()) {
			$invalidFields[] = 'status';
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
	 * @param ExportRepresentation $representation
	 */
	private function validateRequiredFields(ExportRepresentation $representation): void
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

	private function validateNonNullableFields(ExportRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsFieldsSet() && is_null($representation->getFields())) {
			$invalidFields[] = 'fields';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIncludeByteOrderMarkSet() && is_null($representation->getIncludeByteOrderMark())) {
			$invalidFields[] = 'includeByteOrderMark';
		}

		if ($representation->getIsIsExpiredSet() && is_null($representation->getIsExpired())) {
			$invalidFields[] = 'isExpired';
		}

		if ($representation->getIsMaxFileSizeBytesSet() && is_null($representation->getMaxFileSizeBytes())) {
			$invalidFields[] = 'maxFileSizeBytes';
		}

		if ($representation->getIsProcedureSet() && is_null($representation->getProcedure())) {
			$invalidFields[] = 'procedure';
		}

		if ($representation->getIsResultRefsSet() && is_null($representation->getResultRefs())) {
			$invalidFields[] = 'resultRefs';
		}

		if ($representation->getIsStatusSet() && is_null($representation->getStatus())) {
			$invalidFields[] = 'status';
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
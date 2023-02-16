<?php
namespace Api\Config\Objects\Import\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ImportRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class ImportRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof ImportRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ImportRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof ImportRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				ImportRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param ImportRepresentation $representation
	 */
	private function validateReadOnlyFields(ImportRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsBatchesRefSet()) {
			$invalidFields[] = 'batchesRef';
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

		if ($representation->getIsCreatedCountSet()) {
			$invalidFields[] = 'createdCount';
		}

		if ($representation->getIsErrorCountSet()) {
			$invalidFields[] = 'errorCount';
		}

		if ($representation->getIsErrorsRefSet()) {
			$invalidFields[] = 'errorsRef';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsExpiredSet()) {
			$invalidFields[] = 'isExpired';
		}

		if ($representation->getIsOriginSet()) {
			$invalidFields[] = 'origin';
		}

		if ($representation->getIsUpdatedAtSet()) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedCountSet()) {
			$invalidFields[] = 'updatedCount';
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
	 * @param ImportRepresentation $representation
	 */
	private function validateRequiredFields(ImportRepresentation $representation): void
	{
		$invalidFields = [];

		if (!$representation->getIsObjectSet()) {
			$invalidFields[] = 'object';
		}

		if (!$representation->getIsOperationSet()) {
			$invalidFields[] = 'operation';
		}


		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	private function validateNonNullableFields(ImportRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsBatchesRefSet() && is_null($representation->getBatchesRef())) {
			$invalidFields[] = 'batchesRef';
		}

		if ($representation->getIsCreateOnNoMatchSet() && is_null($representation->getCreateOnNoMatch())) {
			$invalidFields[] = 'createOnNoMatch';
		}

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsCreatedCountSet() && is_null($representation->getCreatedCount())) {
			$invalidFields[] = 'createdCount';
		}

		if ($representation->getIsErrorCountSet() && is_null($representation->getErrorCount())) {
			$invalidFields[] = 'errorCount';
		}

		if ($representation->getIsErrorsRefSet() && is_null($representation->getErrorsRef())) {
			$invalidFields[] = 'errorsRef';
		}

		if ($representation->getIsFieldsSet() && is_null($representation->getFields())) {
			$invalidFields[] = 'fields';
		}

		if ($representation->getIsIdSet() && is_null($representation->getId())) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsExpiredSet() && is_null($representation->getIsExpired())) {
			$invalidFields[] = 'isExpired';
		}

		if ($representation->getIsObjectSet() && is_null($representation->getObject())) {
			$invalidFields[] = 'object';
		}

		if ($representation->getIsOperationSet() && is_null($representation->getOperation())) {
			$invalidFields[] = 'operation';
		}

		if ($representation->getIsOriginSet() && is_null($representation->getOrigin())) {
			$invalidFields[] = 'origin';
		}

		if ($representation->getIsRestoreDeletedSet() && is_null($representation->getRestoreDeleted())) {
			$invalidFields[] = 'restoreDeleted';
		}

		if ($representation->getIsStatusSet() && is_null($representation->getStatus())) {
			$invalidFields[] = 'status';
		}

		if ($representation->getIsUpdatedAtSet() && is_null($representation->getUpdatedAt())) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedCountSet() && is_null($representation->getUpdatedCount())) {
			$invalidFields[] = 'updatedCount';
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

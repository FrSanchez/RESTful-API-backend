<?php
namespace Api\Config\Objects\TaggedObject\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\TaggedObjectRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class TaggedObjectRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof TaggedObjectRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				TaggedObjectRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof TaggedObjectRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				TaggedObjectRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param TaggedObjectRepresentation $representation
	 */
	private function validateReadOnlyFields(TaggedObjectRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsCreatedAtSet()) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet()) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsTagNameSet()) {
			$invalidFields[] = 'tagName';
		}

		if ($representation->getIsTargetIdSet()) {
			$invalidFields[] = 'targetId';
		}

		if ($representation->getIsTargetObjectTypeSet()) {
			$invalidFields[] = 'targetObjectType';
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
	 * @param TaggedObjectRepresentation $representation
	 */
	private function validateRequiredFields(TaggedObjectRepresentation $representation): void
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

	private function validateNonNullableFields(TaggedObjectRepresentation $representation): void
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

		if ($representation->getIsTagNameSet() && is_null($representation->getTagName())) {
			$invalidFields[] = 'tagName';
		}

		if ($representation->getIsTargetIdSet() && is_null($representation->getTargetId())) {
			$invalidFields[] = 'targetId';
		}

		if ($representation->getIsTargetObjectTypeSet() && is_null($representation->getTargetObjectType())) {
			$invalidFields[] = 'targetObjectType';
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

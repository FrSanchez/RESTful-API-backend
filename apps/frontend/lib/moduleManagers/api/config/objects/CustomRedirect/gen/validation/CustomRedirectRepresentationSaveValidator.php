<?php
namespace Api\Config\Objects\CustomRedirect\Gen\Validation;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\CustomRedirectRepresentation;
use Api\Representations\Representation;
use Api\Validation\RepresentationSaveValidator;
use ApiErrorLibrary;
use RuntimeException;
use RESTClient;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class CustomRedirectRepresentationSaveValidator implements RepresentationSaveValidator
{
	public function validateCreate(Representation $representation): void
	{
		if (!($representation instanceof CustomRedirectRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				CustomRedirectRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateRequiredFields($representation);
		$this->validateNonNullableFields($representation);
	}

	public function validatePatchUpdate(Representation $representation): void
	{
		if (!($representation instanceof CustomRedirectRepresentation)) {
			throw new RuntimeException(
				"Unexpected representation specified.\nExpected: " .
				CustomRedirectRepresentation::class .
				"\nActual: " . get_class($representation)
			);
		}

		$this->validateReadOnlyFields($representation);
		$this->validateNonNullableFields($representation);
	}

	/**
	 * @param CustomRedirectRepresentation $representation
	 */
	private function validateReadOnlyFields(CustomRedirectRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsBitlyIsPersonalizedSet()) {
			$invalidFields[] = 'bitlyIsPersonalized';
		}

		if ($representation->getIsBitlyShortUrlSet()) {
			$invalidFields[] = 'bitlyShortUrl';
		}

		if ($representation->getIsCampaignSet()) {
			$invalidFields[] = 'campaign';
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

		if ($representation->getIsFolderSet()) {
			$invalidFields[] = 'folder';
		}

		if ($representation->getIsIdSet()) {
			$invalidFields[] = 'id';
		}

		if ($representation->getIsIsDeletedSet()) {
			$invalidFields[] = 'isDeleted';
		}

		if ($representation->getIsSalesforceIdSet()) {
			$invalidFields[] = 'salesforceId';
		}

		if ($representation->getIsTrackedUrlSet()) {
			$invalidFields[] = 'trackedUrl';
		}

		if ($representation->getIsTrackerDomainSet()) {
			$invalidFields[] = 'trackerDomain';
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

		if ($representation->getIsVanityUrlSet()) {
			$invalidFields[] = 'vanityUrl';
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
	 * @param CustomRedirectRepresentation $representation
	 */
	private function validateRequiredFields(CustomRedirectRepresentation $representation): void
	{
		$invalidFields = [];

		if (!$representation->getIsCampaignIdSet()) {
			$invalidFields[] = 'campaignId';
		}

		if (!$representation->getIsDestinationUrlSet()) {
			$invalidFields[] = 'destinationUrl';
		}

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

	private function validateNonNullableFields(CustomRedirectRepresentation $representation): void
	{
		$invalidFields = [];

		if ($representation->getIsBitlyIsPersonalizedSet() && is_null($representation->getBitlyIsPersonalized())) {
			$invalidFields[] = 'bitlyIsPersonalized';
		}

		if ($representation->getIsBitlyShortUrlSet() && is_null($representation->getBitlyShortUrl())) {
			$invalidFields[] = 'bitlyShortUrl';
		}

		if ($representation->getIsCampaignIdSet() && is_null($representation->getCampaignId())) {
			$invalidFields[] = 'campaignId';
		}

		if ($representation->getIsCreatedAtSet() && is_null($representation->getCreatedAt())) {
			$invalidFields[] = 'createdAt';
		}

		if ($representation->getIsCreatedByIdSet() && is_null($representation->getCreatedById())) {
			$invalidFields[] = 'createdById';
		}

		if ($representation->getIsDestinationUrlSet() && is_null($representation->getDestinationUrl())) {
			$invalidFields[] = 'destinationUrl';
		}

		if ($representation->getIsFolderIdSet() && is_null($representation->getFolderId())) {
			$invalidFields[] = 'folderId';
		}

		if ($representation->getIsGaCampaignSet() && is_null($representation->getGaCampaign())) {
			$invalidFields[] = 'gaCampaign';
		}

		if ($representation->getIsGaContentSet() && is_null($representation->getGaContent())) {
			$invalidFields[] = 'gaContent';
		}

		if ($representation->getIsGaMediumSet() && is_null($representation->getGaMedium())) {
			$invalidFields[] = 'gaMedium';
		}

		if ($representation->getIsGaSourceSet() && is_null($representation->getGaSource())) {
			$invalidFields[] = 'gaSource';
		}

		if ($representation->getIsGaTermSet() && is_null($representation->getGaTerm())) {
			$invalidFields[] = 'gaTerm';
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

		if ($representation->getIsTrackedUrlSet() && is_null($representation->getTrackedUrl())) {
			$invalidFields[] = 'trackedUrl';
		}

		if ($representation->getIsTrackerDomainIdSet() && is_null($representation->getTrackerDomainId())) {
			$invalidFields[] = 'trackerDomainId';
		}

		if ($representation->getIsUpdatedAtSet() && is_null($representation->getUpdatedAt())) {
			$invalidFields[] = 'updatedAt';
		}

		if ($representation->getIsUpdatedByIdSet() && is_null($representation->getUpdatedById())) {
			$invalidFields[] = 'updatedById';
		}

		if ($representation->getIsVanityUrlSet() && is_null($representation->getVanityUrl())) {
			$invalidFields[] = 'vanityUrl';
		}

		if ($representation->getIsVanityUrlPathSet() && is_null($representation->getVanityUrlPath())) {
			$invalidFields[] = 'vanityUrlPath';
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

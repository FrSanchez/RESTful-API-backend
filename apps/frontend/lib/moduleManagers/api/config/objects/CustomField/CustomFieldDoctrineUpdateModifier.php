<?php
namespace Api\Config\Objects\CustomField;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\CustomFieldRepresentation;
use Api\Objects\Doctrine\DoctrineUpdateContext;
use Api\Objects\Doctrine\DoctrineUpdateModifier;
use Pardot\ProspectField\ProspectFieldCustomSaveManager;
use Exception;
use PardotLogger;
use ApiErrorLibrary;
use RESTClient;
use piProspectFieldCustom;

class CustomFieldDoctrineUpdateModifier implements DoctrineUpdateModifier
{
	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void
	{
		/** @var piProspectFieldCustom $piProspectFieldCustom */
		$piProspectFieldCustom = $updateContext->getDoctrineRecord();

		$representation = $updateContext->getRepresentation();
		if (!($representation instanceof CustomFieldRepresentation)) {
			PardotLogger::getInstance()->error("The requested object to CustomFieldDoctrineUpdateModifier is not of CustomFieldRepresentation");
			throw new ApiException(ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR, "Invalid input", RESTClient::HTTP_BAD_REQUEST);
		}

		if ($representation->getIsValuesPrefillSet()) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				"valuesPrefill can only be set on POST create requests.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$prospectFieldCustomSaveManager = new ProspectFieldCustomSaveManager($updateContext->getVersion());
		$prospectFieldCustomSaveManager->validateUpdate(
			$updateContext->getUser(),
			$representation,
			$piProspectFieldCustom
		);

		$prospectFieldCustomSaveManager->executeUpdate($updateContext->getUser(), $representation, $piProspectFieldCustom);
	}
}

<?php


namespace Api\Config\Objects\Visitor\RecordActions;
use Api\Config\Objects\Visitor\Gen\RecordActions\AbstractVisitorIdentifyCompanyAction;
use Api\Exceptions\ApiException;
use Api\Gen\Representations\IdentifiedCompanyRepresentation;
use Api\Gen\Representations\IdentifiedCompanyRepresentationBuilder;
use Api\Objects\RecordActions\RecordActionContext;
use piVisitorTable;
use WhoisLookupManager;
use ApiErrorLibrary;
use RESTClient;

class VisitorIdentifyCompanyRecordAction extends AbstractVisitorIdentifyCompanyAction
{
	public function executeActionWithArgs(RecordActionContext $recordActionContext): IdentifiedCompanyRepresentation {

		$id = $recordActionContext->getRecordId();
		$accountId = $recordActionContext->getAccountId();
		$visitor = piVisitorTable::retrieveByIds($id, $accountId);

		if (!$visitor->is_identified) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_VISITOR,
				"Specified Visitor is not identified.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		if (!$visitor->ip_address) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_VISITOR,
				"Specified Visitor does not have a value for the ipAddress field.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$whoisLookupManager = new WhoisLookupManager();
		$visitorWhois = $whoisLookupManager->performIpLookup($visitor->ip_address);

		$builder = new IdentifiedCompanyRepresentationBuilder();
		$builder->setName($visitorWhois->getCompany());
		$builder->setCity($visitorWhois->getCity());
		$builder->setState($visitorWhois->getState());
		$builder->setPostalCode($visitorWhois->getZip());
		$builder->setCountry($visitorWhois->getCountry());

		return $builder->build();
	}


}

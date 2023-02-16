<?php

namespace Api\Config\Objects\Prospect\ObjectActions;

use Api\Exceptions\ApiException;
use Api\Gen\Representations\ProspectRepresentation;
use Api\Objects\ObjectActions\ObjectActionContext;
use Api\Config\Objects\Prospect\Gen\ObjectActions\AbstractProspectUndeleteAction;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\ObjectQueryManager;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\SingleResultQuery;
use Abilities;
use ApiErrorLibrary;
use piProspectTable;
use ProspectPeer;
use ProspectSaveManager;
use RESTClient;
use RuntimeException;
use sfContext;

class ProspectUndeleteAction extends AbstractProspectUndeleteAction
{
	public function __construct(
		?ObjectDefinitionCatalog $objectDefinitionCatalog = null,
		?ObjectQueryManager $objectQueryManager = null
	)
	{
		$this->objectDefinitionCatalog = $objectDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');
		$this->objectQueryManager = $objectQueryManager ?? sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');
	}

	/**
	 * @inheritDoc
	 */
	public function validateWithArgs(
		ObjectActionContext $objectActionContext,
		?string $email,
		?int $id
	): void
	{
		if ($email && $id) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				"Both Email and Id specified on undelete action. Only one may be specified.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		if (!$email && !$id) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				"Email or Id must be specified on undelete action.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public function executeActionWithArgs(ObjectActionContext $objectActionContext, ?string $email, ?int $id): ProspectRepresentation
	{
		$prospect = $id ?
			ProspectPeer::retrieveArchivedByIds($id, $objectActionContext->getAccountId()) :
			ProspectPeer::retrieveArchivedByEmailWithMostRecentActivity($email, $objectActionContext->getAccountId());
		if (!$prospect) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_PROSPECT_NOT_FOUND,
				"Specified deleted prospect not found.",
				RESTClient::HTTP_NOT_FOUND
			);
		}

		$user = $objectActionContext->getAccessContext()->getUser();
		$prospectSaveManager = new ProspectSaveManager($objectActionContext->getAccountId(), $objectActionContext->getApiActions());
		$prospectSaveManager->validateCanAccessProspect($prospect, $user);
		$prospectSaveManager->undeleteProspect($prospect, $user);

		return $this->loadProspectRepresentationById($objectActionContext, $prospect->getId());
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param int $visitorId
	 * @return ProspectRepresentation
	 * @throws Doctrine_Exception
	 */
	protected function loadProspectRepresentationById(ObjectActionContext $objectActionContext, int $id): ProspectRepresentation
	{
		$objectDefinition = $this->objectDefinitionCatalog-> findObjectDefinitionByObjectType(
			$objectActionContext->getVersion(),
			$objectActionContext->getAccessContext()->getAccountId(),
			'Prospect');
		$query = SingleResultQuery::from($objectActionContext->getAccessContext()->getAccountId(), $objectDefinition)
			->addSelections(
				$objectDefinition->getFieldByName('id'),
				$objectDefinition->getFieldByName('email'),
				$objectDefinition->getFieldByName('isDeleted')
			)
			->addWhereEquals('id', $id);
		$queryContext = new QueryContext($objectActionContext->getAccessContext()->getAccountId(), $objectActionContext->getVersion(), $objectActionContext->getAccessContext());
		$result = $this->objectQueryManager->queryOne($queryContext, $query);
		$representation = $result->getRepresentation();
		if (is_null($representation)) {
			// Record action framework ensures it exists before this point so this error should be limited to race conditions.
			throw new RuntimeException('Failed to find prospect with ID: ' . $id);
		}
		return $representation;
	}
}

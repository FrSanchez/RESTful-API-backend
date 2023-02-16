<?php
namespace Api\Config\Objects\Visitor\RecordActions;

use Api\Config\Objects\Visitor\Gen\RecordActions\AbstractVisitorAssignToProspectAction;
use Api\Exceptions\ApiException;
use Api\Gen\Representations\VisitorRepresentation;
use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\ObjectQueryManager;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\SingleResultQuery;
use Api\Objects\RecordActions\RecordActionContext;
use ApiErrorLibrary;
use Doctrine_Exception;
use Exception;
use piProspect;
use piProspectTable;
use RESTClient;
use RuntimeException;
use sfContext;
use VisitorProspectAssignManager;

class VisitorAssignToProspectRecordAction extends AbstractVisitorAssignToProspectAction
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;
	private piProspectTable $piProspectTable;
	private VisitorProspectAssignManager $visitorProspectAssignManager;
	private ObjectQueryManager $objectQueryManager;

	private piProspect $piProspect;

	public function __construct(
		?ObjectDefinitionCatalog $objectDefinitionCatalog = null,
		?ObjectQueryManager $objectQueryManager = null,
		?piProspectTable $piProspectTable = null,
		?VisitorProspectAssignManager $visitorProspectAssignManager = null
	) {
		$this->objectDefinitionCatalog = $objectDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');
		$this->objectQueryManager = $objectQueryManager ?? sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');
		$this->piProspectTable = $piProspectTable ?? piProspectTable::getInstance();
		$this->visitorProspectAssignManager = $visitorProspectAssignManager ?? new VisitorProspectAssignManager();
	}

	public function validateWithArgs(RecordActionContext $recordActionContext, ?bool $assignDeletedProspect, int $prospectId): void
	{
		parent::validateWithArgs($recordActionContext, $assignDeletedProspect, $prospectId);

		if (is_null($assignDeletedProspect)) {
			$assignDeletedProspect = false;
		}

		/** @var piProspect|false $piProspect */
		$piProspect = $this->piProspectTable->retrieveOneById($prospectId, $recordActionContext->getAccountId());
		if (!$piProspect) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROSPECT_ID,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$this->piProspect = $piProspect;
		if (!$assignDeletedProspect && $this->piProspect->is_archived) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROSPECT_ID,
				"Prospect is in recycle bin.",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param RecordActionContext $recordActionContext,
	 * @param bool|null $assignDeletedProspect
	 * @param int $prospectId
	 * @return VisitorRepresentation
	 * @throws Exception
	 */
	public function executeActionWithArgs(
		RecordActionContext $recordActionContext,
		?bool $assignDeletedProspect,
		int $prospectId
	): VisitorRepresentation {
		$visitorId = $recordActionContext->getRecordId();

		$this->visitorProspectAssignManager->assignProspect($visitorId, $this->piProspect);

		return $this->loadVisitorRepresentationById($recordActionContext->getVersion(), $recordActionContext->getAccessContext(), $visitorId);
	}

	/**
	 * @param int $version
	 * @param AccessContext $accessContext
	 * @param int $visitorId
	 * @return VisitorRepresentation
	 * @throws Doctrine_Exception
	 */
	private function loadVisitorRepresentationById(int $version, AccessContext $accessContext, int $visitorId): VisitorRepresentation
	{
		$visitorObjectDefinition = $this->objectDefinitionCatalog-> findObjectDefinitionByObjectType(
			$version,
			$accessContext->getAccountId(),
			'Visitor');
		$query = SingleResultQuery::from($accessContext->getAccountId(), $visitorObjectDefinition)
			->addSelections(
				$visitorObjectDefinition->getFieldByName('id'),
				$visitorObjectDefinition->getFieldByName('prospectId'),
			)
			->addWhereEquals('id', $visitorId);
		$queryContext = new QueryContext($accessContext->getAccountId(), $version, $accessContext);
		$result = $this->objectQueryManager->queryOne($queryContext, $query);
		$representation = $result->getRepresentation();
		if (is_null($representation)) {
			// This should never happen since the record action framework ensures it exists before this point however
			// let's fail if it happens.
			throw new RuntimeException('Failed to find visitor with ID: ' . $visitorId);
		}
		if (!($representation instanceof VisitorRepresentation)) {
			throw new RuntimeException('Query failed to return a VisitorRepresentation as requested. actual: ' . get_class($representation));
		}
		return $representation;
	}
}

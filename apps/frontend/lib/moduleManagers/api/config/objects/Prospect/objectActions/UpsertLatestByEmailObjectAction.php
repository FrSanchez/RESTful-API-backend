<?php
namespace Api\Config\Objects\Prospect\ObjectActions;

use Api\Config\Objects\Prospect\Gen\Validation\ProspectRepresentationSaveValidator;
use Api\Exceptions\ApiException;
use Api\Framework\ApiRequest;
use Api\Objects\Access\AccessContext;
use Api\Objects\FieldsParser;
use Api\Objects\ObjectActions\ObjectActionContext;
use Api\Config\Objects\Prospect\Gen\ObjectActions\AbstractProspectUpsertLatestByEmailAction;
use Api\Gen\Representations\ProspectRepresentation;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\ObjectQueryManager;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\SingleResultQuery;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use Doctrine_Transaction_Exception;
use Doctrine_Validator_Exception;
use Exception;
use PropelException;
use ProspectPeer;
use ProspectSaveManager;
use Prospect;
use ValidationException;
use sfContext;
use RuntimeException;
use ProspectDeduplicationManager;
use ApiErrorLibrary;
use RESTClient;

class UpsertLatestByEmailObjectAction extends AbstractProspectUpsertLatestByEmailAction
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;
	private ObjectQueryManager $objectQueryManager;
	private FieldsParser $fieldsParser;

	public function __construct(
		?ObjectDefinitionCatalog $objectDefinitionCatalog = null,
		?ObjectQueryManager $objectQueryManager = null,
		?FieldsParser $fieldsParser = null
	) {
		$this->objectDefinitionCatalog = $objectDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.objects.objectDefinitionCatalog');
		$this->objectQueryManager = $objectQueryManager ?? sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');
		$this->fieldsParser = $fieldsParser ?? sfContext::getInstance()->getContainer()->get('api.objects.fieldsParser');
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param array|null $fields
	 * @param string $matchEmail
	 * @param ProspectRepresentation $prospectRepresentation
	 * @param bool|null $secondaryDeletedSearch
	 * @throws Exception
	 */
	public function validateWithArgs(
		ObjectActionContext $objectActionContext,
		?array $fields,
		string $matchEmail,
		ProspectRepresentation $prospectRepresentation,
		?bool $secondaryDeletedSearch
	): void {
		parent::validateWithArgs($objectActionContext, $fields, $matchEmail, $prospectRepresentation, $secondaryDeletedSearch);

		$prospectSaveValidator = new ProspectRepresentationSaveValidator();
		$prospectSaveValidator->validateCreate($prospectRepresentation);
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param array|null $fields
	 * @param string $matchEmail
	 * @param ProspectRepresentation $prospectRepresentation
	 * @param bool|null $secondaryDeletedSearch
	 * @return ProspectRepresentation|null
	 * @throws Doctrine_Exception
	 * @throws PropelException
	 * @throws ValidationException
	 * @throws Doctrine_Transaction_Exception
	 * @throws Doctrine_Validator_Exception
	 * @throws Exception
	 */
	public function executeActionWithArgs(
		ObjectActionContext $objectActionContext,
		?array $fields,
		string $matchEmail,
		ProspectRepresentation $prospectRepresentation,
		?bool $secondaryDeletedSearch
	): ?ProspectRepresentation {
		$prospectObjectDefinition = $this->objectDefinitionCatalog->findObjectDefinitionByObjectType(
			$objectActionContext->getVersion(),
			$objectActionContext->getAccountId(),
			'Prospect'
		);

		$selections = $this->getSelections($fields, $prospectObjectDefinition, $objectActionContext->getApiRequest());
		if (is_null($secondaryDeletedSearch)) {
			$secondaryDeletedSearch = true; // default has to be true
		}

		$isMultiplicityEnabled = ProspectDeduplicationManager::getInstance($objectActionContext->getAccountId())->isMultiplicityEnabled();
		if (!$isMultiplicityEnabled || $secondaryDeletedSearch) {
			$prospect = ProspectPeer::retrieveByEmailWithMostRecentActivity(
				$matchEmail,
				$objectActionContext->getAccountId(),
				true
			);
		} else { // only have to deal with no archived prospects
			$prospect = ProspectPeer::retrieveByEmailWithMostRecentActivity(
				$matchEmail,
				$objectActionContext->getAccountId(),
				false
			);
		}

		$prospectSaveManager = new ProspectSaveManager(
			$objectActionContext->getAccountId(),
			$objectActionContext->getApiActions()
		);

		// Assumes prospect upsert is by account and not by the prospect that user has access to. Therefore, this check is required.
		$user = $objectActionContext->getAccessContext()->getUser();
		if ($prospect) {
			$prospectSaveManager->validateCanAccessProspect($prospect, $user);
		}

		// if the latest activity prospect is archived, we need to undelete it
		if ($prospect && $prospect->getIsArchived()) {
			$prospectSaveManager->undeleteProspect($prospect, $user);
		}

		// transactions are built into the framework itself
		if (!$prospect) {
			$prospect = $this->upsertWithProspectCreate(
				$objectActionContext,
				$prospectRepresentation,
				$prospectSaveManager
			);
		} else {
			$prospect = $this->upsertWithProspectUpdate(
				$objectActionContext,
				$prospectRepresentation,
				$prospect->getId(),
				$prospectSaveManager
			);
		}

		return $this->loadProspectRepresentationById(
			$objectActionContext->getVersion(),
			$objectActionContext->getAccessContext(),
			$prospect->getId(),
			$prospectObjectDefinition,
			$selections
		);
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param ProspectRepresentation $prospectRepresentation
	 * @param int $prospectId
	 * @param ProspectSaveManager $prospectSaveManager
	 * @return Prospect
	 * @throws Doctrine_Exception
	 * @throws PropelException
	 * @throws ValidationException
	 */
	private function upsertWithProspectUpdate(
		ObjectActionContext $objectActionContext,
		ProspectRepresentation $prospectRepresentation,
		int $prospectId,
		ProspectSaveManager $prospectSaveManager
	): Prospect {
		$prospect = $prospectSaveManager->validateUpdate(
			$objectActionContext->getAccessContext()->getUser(),
			$prospectRepresentation,
			[
				SystemColumnNames::ID => $prospectId
			]
		);

		return $prospectSaveManager->performUpdate(
			$objectActionContext->getAccessContext()->getUser(),
			$prospectRepresentation,
			$prospect,
			[],
			[],
			false
		);
	}

	/**
	 * @param ObjectActionContext $objectActionContext
	 * @param ProspectRepresentation $prospectRepresentation
	 * @param ProspectSaveManager $prospectSaveManager
	 * @return Prospect
	 * @throws Exception
	 */
	private function upsertWithProspectCreate(
		ObjectActionContext $objectActionContext,
		ProspectRepresentation $prospectRepresentation,
		ProspectSaveManager $prospectSaveManager
	): Prospect {
		$prospectSaveManager->validateCreate(
			$objectActionContext->getAccessContext()->getUser(),
			$prospectRepresentation
		);

		return $prospectSaveManager->performCreate(
			$objectActionContext->getAccessContext()->getUser(),
			$prospectRepresentation,
			[],
			[],
			false
		);
	}

	/**
	 * @param array|null $fields
	 * @param ObjectDefinition $prospectObjectDefinition
	 * @param ApiRequest $apiRequest
	 * @return array
	 */
	private function getSelections(
		?array $fields,
		ObjectDefinition $prospectObjectDefinition,
		ApiRequest $apiRequest
	): array {
		if (is_null($fields) || empty($fields)) {
			return array(
				$prospectObjectDefinition->getFieldByName('id'),
				$prospectObjectDefinition->getFieldByName('email'),
			);
		}

		return $this->fieldsParser->parseFields(
			$apiRequest,
			$fields,
			$prospectObjectDefinition,
			false,
			false
		);
	}

	/**
	 * @param int $version
	 * @param AccessContext $accessContext
	 * @param int $prospectId
	 * @param ObjectDefinition $prospectObjectDefinition
	 * @param array $selections
	 * @return ProspectRepresentation|null
	 * @throws Doctrine_Exception
	 */
	private function loadProspectRepresentationById(
		int $version,
		AccessContext $accessContext,
		int $prospectId,
		ObjectDefinition $prospectObjectDefinition,
		array $selections
	): ?ProspectRepresentation {
		$query = SingleResultQuery::from($accessContext->getAccountId(), $prospectObjectDefinition)
			->addSelections(...$selections)
			->addWhereEquals('id', $prospectId);
		$queryContext = new QueryContext($accessContext->getAccountId(), $version, $accessContext);

		$result = $this->objectQueryManager->queryOne($queryContext, $query);
		$representation = $result->getRepresentation();

		if (is_null($representation)) {
			// This would happen if the prospect was updated to where user does not have access to it anymore!
			return null;
		}

		if (!($representation instanceof ProspectRepresentation)) {
			throw new RuntimeException('Query failed to return a ProspectRepresentation as requested. actual: ' . get_class($representation));
		}

		return $representation;
	}
}

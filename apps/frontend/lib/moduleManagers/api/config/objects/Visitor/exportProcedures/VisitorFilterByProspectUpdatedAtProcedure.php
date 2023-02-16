<?php
namespace Api\Config\Objects\Visitor\ExportProcedures;

use Api\Config\Objects\Visitor\Gen\ExportProcedures\AbstractVisitorFilterByProspectUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class VisitorFilterByProspectUpdatedAtProcedure extends AbstractVisitorFilterByProspectUpdatedAtProcedure
{
	private bool $isInternalRequest;

	/**
	 * @param ProcedureDefinition $definition
	 * @param int $version
	 * @param int $accountId
	 * @param array $arguments
	 * @param bool $isInternalRequest
	 * @throws Exception
	 */
	public function __construct(
		ProcedureDefinition $definition,
		int $version,
		int $accountId,
		array $arguments,
		bool $isInternalRequest
	) {
		$this->isInternalRequest = $isInternalRequest;
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param string|null $filterType
	 * @param mixed $prospectDeleted
	 * @param DateTime $prospectUpdatedAfter
	 * @param DateTime|null $prospectUpdatedBefore
	 * @return Doctrine_Query
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		$prospectDeleted,
		DateTime $prospectUpdatedAfter,
		?DateTime $prospectUpdatedBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$rootAlias = $query->getRootAlias();

		/** Add the related Prospect to the query */
		$prospectAlias = 'rp';
		$query
			->innerJoin($rootAlias . '.piProspect ' . $prospectAlias)
			->andWhere($prospectAlias . '.account_id = ?', $this->getAccountId());

		DeletedArgumentHelper::applyDeletedToQuery($query, $prospectDeleted, $prospectAlias);
		$updatedAtHelper = DateTimeArgumentHelper::createForUpdatedAt($this->isInternalRequest, $prospectAlias);
		$updatedAtHelper->applyDateRangeToQuery($query, $prospectUpdatedAfter, $prospectUpdatedBefore);

		return $query;
	}

	/**
	 * Validates the DateTime arguments.
	 * @param mixed|null $prospectDeleted
	 * @param DateTime $prospectUpdatedAfter
	 * @param DateTime|null $prospectUpdatedBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		$prospectDeleted,
		DateTime $prospectUpdatedAfter,
		?DateTime $prospectUpdatedBefore
	): void {
		parent::validateWithArgs($prospectDeleted, $prospectUpdatedAfter, $prospectUpdatedBefore);
		$updatedAtHelper = DateTimeArgumentHelper::createForUpdatedAt($this->isInternalRequest);
		$updatedAtHelper->validateDateRange($prospectUpdatedAfter, $prospectUpdatedBefore);
		DeletedArgumentHelper::validateDeletedArgument($prospectDeleted);
	}
}

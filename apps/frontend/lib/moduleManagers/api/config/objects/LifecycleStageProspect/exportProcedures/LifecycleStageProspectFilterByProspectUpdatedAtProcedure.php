<?php
namespace Api\Config\Objects\LifecycleStageProspect\ExportProcedures;

use Api\Config\Objects\LifecycleStageProspect\Gen\ExportProcedures\AbstractLifecycleStageProspectFilterByProspectUpdatedAtProcedure;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Api\Export\ProcedureDefinition;
use Exception;

class LifecycleStageProspectFilterByProspectUpdatedAtProcedure extends AbstractLifecycleStageProspectFilterByProspectUpdatedAtProcedure
{
	private bool $isInternalRequest;

	/**
	 * LifecycleStageProspectFilterByProspectUpdatedAtProcedure constructor.
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
	 * @param QueryContext $queryContext
	 * @param array $selectedFields
	 * @param string|bool|null $prospectDeleted
	 * @param DateTime $prospectUpdatedAfter
	 * @param DateTime|null $prospectUpdatedBefore
	 * @return Doctrine_Query
	 * @throws Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		$prospectDeleted,
		DateTime $prospectUpdatedAfter,
		?DateTime $prospectUpdatedBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()
			->createDoctrineQuery($queryContext, $selectedFields);

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
	 * @param string|bool|null $prospectDeleted
	 * @param DateTime|null $prospectUpdatedAfter
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

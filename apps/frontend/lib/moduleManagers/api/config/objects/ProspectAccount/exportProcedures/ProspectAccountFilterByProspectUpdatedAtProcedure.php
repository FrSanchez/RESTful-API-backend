<?php
namespace Api\Config\Objects\ProspectAccount\ExportProcedures;

use Api\Config\Objects\ProspectAccount\Gen\ExportProcedures\AbstractProspectAccountFilterByProspectUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use dateTools;
use Exception;
use DateTime;
use Doctrine_Query;

class ProspectAccountFilterByProspectUpdatedAtProcedure extends AbstractProspectAccountFilterByProspectUpdatedAtProcedure
{
	private DateTimeArgumentHelper $updatedAtHelper;

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
		$this->updatedAtHelper = DateTimeArgumentHelper::createForUpdatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param array $selectedFields The fields that should be returned from this procedure.
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
			->innerJoin($rootAlias . '.piProspects ' . $prospectAlias)
			->andWhere($prospectAlias . '.account_id = ?', $this->getAccountId());

		DeletedArgumentHelper::applyDeletedToQuery($query, $prospectDeleted, $prospectAlias);

		/** If before/after are both supplied, use BETWEEN vs (> AND <) syntax to avoid DQL 1.x bracketing bugs */
		if (!is_null($prospectUpdatedBefore) && !is_null($prospectUpdatedAfter)) {
			$query->andWhere($prospectAlias . '.updated_at BETWEEN ? AND ?',
				[dateTools::mysqlFormat($prospectUpdatedAfter), dateTools::mysqlFormat($prospectUpdatedBefore)]);
		} else {
			if (!is_null($prospectUpdatedAfter)) {
				$query->andWhere($prospectAlias . '.updated_at > ?', dateTools::mysqlFormat($prospectUpdatedAfter));
			}
			if (!is_null($prospectUpdatedBefore)) {
				$query->andWhere($prospectAlias . '.updated_at < ?)', dateTools::mysqlFormat($prospectUpdatedBefore));
			}
		}

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
		$this->updatedAtHelper->validateDateRange($prospectUpdatedAfter, $prospectUpdatedBefore);
		DeletedArgumentHelper::validateDeletedArgument($prospectDeleted);
	}
}

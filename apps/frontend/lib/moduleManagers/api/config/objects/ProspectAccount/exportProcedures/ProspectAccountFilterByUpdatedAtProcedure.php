<?php
namespace Api\Config\Objects\ProspectAccount\ExportProcedures;

use Api\Config\Objects\ProspectAccount\Gen\ExportProcedures\AbstractProspectAccountFilterByUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class ProspectAccountFilterByUpdatedAtProcedure extends AbstractProspectAccountFilterByUpdatedAtProcedure
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
	 * @param string|bool|null $deleted
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @return Doctrine_Query
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		$deleted,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$this->updatedAtHelper->applyDateRangeToQuery($query, $updatedAfter, $updatedBefore);
		DeletedArgumentHelper::applyDeletedToQuery($query, $deleted);

		return $query;
	}

	/**
	 * Validates the DateTime arguments.
	 * @param string|bool|null $deleted
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		$deleted,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): void {
		parent::validateWithArgs($deleted, $updatedAfter, $updatedBefore);
		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);
		DeletedArgumentHelper::validateDeletedArgument($deleted);
	}
}

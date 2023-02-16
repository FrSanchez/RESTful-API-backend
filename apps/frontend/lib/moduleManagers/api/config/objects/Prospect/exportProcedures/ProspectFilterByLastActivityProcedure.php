<?php
namespace Api\Config\Objects\Prospect\ExportProcedures;

use Api\Config\Objects\Prospect\Gen\ExportProcedures\AbstractProspectFilterByLastActivityProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Exception;

class ProspectFilterByLastActivityProcedure extends AbstractProspectFilterByLastActivityProcedure
{
	private DateTimeArgumentHelper $lastActivityAtHelper;

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
		$this->lastActivityAtHelper = DateTimeArgumentHelper::createForLastActivityAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param string|bool|null $deleted
	 * @param DateTime $lastActivityAfter
	 * @param DateTime|null $lastActivityBefore
	 * @return Doctrine_Query
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		$deleted,
		DateTime $lastActivityAfter,
		?DateTime $lastActivityBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$this->lastActivityAtHelper->applyDateRangeToQuery($query, $lastActivityAfter, $lastActivityBefore);
		DeletedArgumentHelper::applyDeletedToQuery($query, $deleted);

		return $query;
	}

	/**
	 * Validates the DateTime argument.
	 * @param bool|string|null $deleted
	 * @param DateTime $lastActivityAfter
	 * @param DateTime|null $lastActivityBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		$deleted,
		DateTime $lastActivityAfter,
		?DateTime $lastActivityBefore
	): void {
		parent::validateWithArgs($deleted, $lastActivityAfter, $lastActivityBefore);

		$this->lastActivityAtHelper->validateDateRange($lastActivityAfter, $lastActivityBefore);

		/**
		 * Due to not having a proper index on last_activity and updated_at,
		 * the prospect bulk export will enforce the validation of the deleted param to be "all" until the proper migrations
		 * are run.
		 *
		 * This will prevent the user from providing the following when executing the filter_by_last_activity and filter_by_updated_at procedures:
		 *
		 * - not providing a deleted param
		 * - providing deleted = true
		 * - providing deleted = false
		 *
		 * Migration work item: W-7260941
		 */
		DeletedArgumentHelper::validateDeletedArgument($deleted);
	}
}

<?php
namespace Api\Config\Objects\Prospect\ExportProcedures;

use Api\Config\Objects\Prospect\Gen\ExportProcedures\AbstractProspectFilterByUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class ProspectFilterByUpdatedAtProcedure extends AbstractProspectFilterByUpdatedAtProcedure
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
	 * @param QueryContext $queryContext
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
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @param string|bool|null $deleted
	 * @throws Exception
	 */
	public function validateWithArgs(
		$deleted,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): void {
		parent::validateWithArgs($deleted, $updatedAfter, $updatedBefore);

		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);

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

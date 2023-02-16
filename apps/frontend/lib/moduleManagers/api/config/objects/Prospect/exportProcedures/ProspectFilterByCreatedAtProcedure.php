<?php
namespace Api\Config\Objects\Prospect\ExportProcedures;

use Api\Config\Objects\Prospect\Gen\ExportProcedures\AbstractProspectFilterByCreatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class ProspectFilterByCreatedAtProcedure extends AbstractProspectFilterByCreatedAtProcedure
{
	private DateTimeArgumentHelper $createdAtHelper;

	/**
	 * @param ProcedureDefinition $definition
	 * @param int $version
	 * @param int $accountId
	 * @param array $argumentsr
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
		$this->createdAtHelper = DateTimeArgumentHelper::createForCreatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param string|bool|null $deleted
	 * @return Doctrine_Query
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		DateTime $createdAfter,
		?DateTime $createdBefore,
		$deleted
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$this->createdAtHelper->applyDateRangeToQuery($query, $createdAfter, $createdBefore);
		DeletedArgumentHelper::applyDeletedToQuery($query, $deleted);

		return $query;
	}

	/**
	 * Validates the DateTime arguments.
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param string|bool|null $deleted
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime $createdAfter,
		?DateTime $createdBefore,
		$deleted
	): void {
		parent::validateWithArgs($createdAfter, $createdBefore, $deleted);

		$this->createdAtHelper->validateDateRange($createdAfter, $createdBefore);

		/**
		 * Due to not having a proper index on last_activity, updated_at, and created_at,
		 * the prospect bulk export will enforce the validation of the deleted param to be "all" until the proper migrations
		 * are run.
		 *
		 * This will prevent the user from providing the following when executing the filter_by_last_activity,
		 * filter_by_updated_at and filter_by_created_at procedures:
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

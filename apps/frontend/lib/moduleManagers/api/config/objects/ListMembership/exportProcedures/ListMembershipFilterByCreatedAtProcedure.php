<?php
namespace Api\Config\Objects\ListMembership\ExportProcedures;

use Api\Config\Objects\ListMembership\Gen\ExportProcedures\AbstractListMembershipFilterByCreatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Exception;

class ListMembershipFilterByCreatedAtProcedure extends AbstractListMembershipFilterByCreatedAtProcedure
{
	private DateTimeArgumentHelper $createdAtHelper;

	/**
	 * ListMembershipCreatedAtProcedure constructor.
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
		$this->createdAtHelper = DateTimeArgumentHelper::createForCreatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param array $selectedFields
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param mixed|null $deleted
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
	 * Validates the DateTime argument.
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param bool|String|null $deleted
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime $createdAfter,
		?DateTime $createdBefore,
		$deleted
	): void {
		parent::validateWithArgs($createdAfter, $createdBefore, $deleted);

		$this->createdAtHelper->validateDateRange($createdAfter, $createdBefore);

		DeletedArgumentHelper::validateDeletedArgument($deleted);
	}
}

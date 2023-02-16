<?php

namespace Api\Config\Objects\Export\ExportProcedures;

use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class AllFilterByCreatedAtProcedure extends BaseAllFilterByProcedure
{
	public const NAME = "filterByCreatedAt";

	public const ARG_CREATED_AFTER = "createdAfter";
	public const ARG_CREATED_BEFORE = "createdBefore";
	public const REQUIRED_FIELD = 'createdAt';

	private DateTimeArgumentHelper $createdAtHelper;

	public function __construct(ProcedureDefinition $definition, int $version, int $accountId, array $arguments, bool $isInternalRequest, string $objectName)
	{
		$this->createdAtHelper = DateTimeArgumentHelper::createForCreatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest, $objectName);
	}

	/**
	 * @inheritDoc
	 */
	final protected function validate(array $arguments): void
	{
		$this->validateWithArgs(
			$arguments[self::ARG_CREATED_AFTER] ?? null,
			$arguments[self::ARG_CREATED_BEFORE] ?? null,
			$arguments[self::ARG_DELETED] ?? null
		);
	}

	/**
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param bool|string|null $deleted
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime  $createdAfter,
		?DateTime $createdBefore,
		$deleted
	): void {
		$this->createdAtHelper->validateDateRange($createdAfter, $createdBefore);
		DeletedArgumentHelper::validateDeletedArgument($deleted);
	}

	/**
	 * @inheritDoc
	 */
	final public function generateDoctrineQuery(QueryContext $queryContext, array $selectedFields): Doctrine_Query
	{
		return $this->generateDoctrineQueryWithArgs(
			$queryContext,
			$selectedFields,
			$this->getArguments()[self::ARG_CREATED_AFTER] ?? null,
			$this->getArguments()[self::ARG_CREATED_BEFORE] ?? null,
			$this->getArguments()[self::ARG_DELETED] ?? null
		);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param bool|string|null $deleted
	 * @return Doctrine_Query
	 * @throws Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array        $selectedFields,
		DateTime     $createdAfter,
		?DateTime    $createdBefore,
		$deleted
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$this->createdAtHelper->applyDateRangeToQuery($query, $createdAfter, $createdBefore);
		if ($this->getSupportsDeleted()) {
			DeletedArgumentHelper::applyDeletedToQuery($query, $deleted);
		}
		return $query;
	}

	/**
	 * @inheritDoc
	 */
	public static function getRequiredObjectField(): string
	{
		return self::REQUIRED_FIELD;
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return self::NAME;
	}
}

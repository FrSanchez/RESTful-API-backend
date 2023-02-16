<?php

namespace Api\Config\Objects\Export\ExportProcedures;

use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Export\Procedures\DeletedArgumentHelper;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class AllFilterByUpdatedAtProcedure extends BaseAllFilterByProcedure
{
	public const NAME = "filterByUpdatedAt";

	public const ARG_UPDATED_AFTER = "updatedAfter";
	public const ARG_UPDATED_BEFORE = "updatedBefore";
	public const REQUIRED_FIELD = 'updatedAt';

	private DateTimeArgumentHelper $updatedAtHelper;

	/**
	 * @param ProcedureDefinition $definition The definition of this procedure.
	 * @param int $version The version of the API
	 * @param int $accountId The account ID that is executing the procedure.
	 * @param array $arguments The arguments specified by the user to be used when executing this procedure.
	 * @param bool $isInternalRequest The request type
	 * @throws Exception
	 */
	public function __construct(ProcedureDefinition $definition, int $version, int $accountId, array $arguments, bool $isInternalRequest, string $objectName)
	{
		$this->updatedAtHelper = DateTimeArgumentHelper::createForUpdatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest, $objectName);
	}

	/**
	 * @param array $arguments
	 * @throws Exception
	 */
	final protected function validate(array $arguments): void
	{
		$this->validateWithArgs(
			$arguments[self::ARG_UPDATED_AFTER] ?? null,
			$arguments[self::ARG_UPDATED_BEFORE] ?? null,
			$arguments[self::ARG_DELETED] ?? null
		);
	}

	/**
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @param bool|string|null $deleted
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime  $updatedAfter,
		?DateTime $updatedBefore,
		$deleted
	): void {
		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);
		DeletedArgumentHelper::validateDeletedArgument($deleted);
	}

	/**
	 * @param QueryContext $queryContext Context in which the query is to be executed in.
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @return Doctrine_Query
	 * @throws Exception
	 */
	final public function generateDoctrineQuery(QueryContext $queryContext, array $selectedFields): Doctrine_Query
	{
		return $this->generateDoctrineQueryWithArgs(
			$queryContext,
			$selectedFields,
			$this->getArguments()[self::ARG_UPDATED_AFTER] ?? null,
			$this->getArguments()[self::ARG_UPDATED_BEFORE] ?? null,
			$this->getArguments()[self::ARG_DELETED] ?? null
		);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @param bool|string|null $deleted
	 * @return Doctrine_Query
	 * @throws Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array        $selectedFields,
		DateTime     $updatedAfter,
		?DateTime    $updatedBefore,
		$deleted
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);
		$this->updatedAtHelper->applyDateRangeToQuery($query, $updatedAfter, $updatedBefore);
		DeletedArgumentHelper::applyDeletedToQuery($query, $deleted);
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

<?php

namespace Api\Config\Objects\LifecycleStageProspect\ExportProcedures;

use Api\Config\Objects\LifecycleStageProspect\Gen\ExportProcedures\AbstractLifecycleStageProspectFilterByUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Exception;

class LifecycleStageProspectFilterByUpdatedAtProcedure extends AbstractLifecycleStageProspectFilterByUpdatedAtProcedure
{
	private DateTimeArgumentHelper $updatedAtHelper;

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
		$this->updatedAtHelper = DateTimeArgumentHelper::createForUpdatedAt($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()
			->createDoctrineQuery($queryContext, $selectedFields);

		$this->updatedAtHelper->applyDateRangeToQuery($query, $updatedAfter, $updatedBefore);

		return $query;
	}

	public function validateWithArgs(
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): void {
		parent::validateWithArgs($updatedAfter, $updatedBefore);
		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);
	}
}

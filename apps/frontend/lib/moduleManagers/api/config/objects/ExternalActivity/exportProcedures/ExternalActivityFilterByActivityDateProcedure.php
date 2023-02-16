<?php
namespace Api\Config\Objects\ExternalActivity\ExportProcedures;

use Api\Config\Objects\ExternalActivity\Gen\ExportProcedures\AbstractExternalActivityFilterByActivityDateProcedure;
use Api\Config\Objects\ExternalActivity\Gen\ExportProcedures\AbstractExternalActivityFilterByCreatedAtProcedure;
use Api\Config\Objects\LifecycleStageProspect\Gen\ExportProcedures\AbstractLifecycleStageProspectFilterByUpdatedAtProcedure;
use Api\Config\Objects\VisitorActivity\ExportProcedures\VisitorActivityTypeArgumentHelper;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Exception;

class ExternalActivityFilterByActivityDateProcedure extends AbstractExternalActivityFilterByActivityDateProcedure
{
	/**
	 * @var DateTimeArgumentHelper
	 */
	private DateTimeArgumentHelper $activityDateHelper;

	/**
	 * ExternalActivityFilterByActivityDateProcedure constructor.
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
		$this->activityDateHelper = DateTimeArgumentHelper::createForActivityDate($isInternalRequest);
		parent::__construct($definition, $version, $accountId, $arguments, $isInternalRequest);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @param DateTime $activityAfter
	 * @param DateTime|null $activityBefore
	 * @return Doctrine_Query
	 * @throws Doctrine_Query_Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		DateTime $activityAfter,
		?DateTime $activityBefore
	): Doctrine_Query
	{
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);
		$this->activityDateHelper->applyDateRangeToQuery($query, $activityAfter, $activityBefore);

		return $query;
	}

	/**
	 * @param DateTime $activityAfter
	 * @param DateTime|null $activityBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime $activityAfter,
		?DateTime $activityBefore
	): void {
		parent::validateWithArgs($activityAfter, $activityBefore);

		ExternalActivityArgumentHelper::isExternalActivityProvisioned($this->getAccountId());
		$this->activityDateHelper->validateDateRange($activityAfter, $activityBefore);
	}
}

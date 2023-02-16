<?php
namespace Api\Config\Objects\ExternalActivity\ExportProcedures;

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

class ExternalActivityFilterByCreatedAtProcedure extends AbstractExternalActivityFilterByCreatedAtProcedure
{
	/**
	 * @var DateTimeArgumentHelper
	 */
	private DateTimeArgumentHelper $createdAtHelper;

	/**
	 * ExternalActivityFilterByCreatedAtProcedure constructor.
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
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @return Doctrine_Query
	 * @throws Doctrine_Query_Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		DateTime $createdAfter,
		?DateTime $createdBefore
	): Doctrine_Query
	{
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);
		$this->createdAtHelper->applyDateRangeToQuery($query, $createdAfter, $createdBefore);

		return $query;
	}

	/**
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime $createdAfter,
		?DateTime $createdBefore
	): void {
		parent::validateWithArgs($createdAfter, $createdBefore);

		ExternalActivityArgumentHelper::isExternalActivityProvisioned($this->getAccountId());
		$this->createdAtHelper->validateDateRange($createdAfter, $createdBefore);
	}
}

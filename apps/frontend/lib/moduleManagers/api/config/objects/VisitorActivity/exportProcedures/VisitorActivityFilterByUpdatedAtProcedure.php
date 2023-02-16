<?php
namespace Api\Config\Objects\VisitorActivity\ExportProcedures;

use Api\Config\Objects\VisitorActivity\Gen\ExportProcedures\AbstractVisitorActivityFilterByUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Doctrine_Query_Exception;
use Exception;
use VisitorActivityConstants;

class VisitorActivityFilterByUpdatedAtProcedure extends AbstractVisitorActivityFilterByUpdatedAtProcedure
{
	/** @var DateTimeArgumentHelper $updatedAtHelper */
	private DateTimeArgumentHelper $updatedAtHelper;

	/**
	 * VisitorActivityUpdatedAtProcedure constructor.
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
	 * @param \Api\Objects\FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @param bool|null $prospectOnly
	 * @param array|null $type
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @return Doctrine_Query
	 * @throws Doctrine_Query_Exception
	 */
	public function generateDoctrineQueryWithArgs(QueryContext $queryContext, array $selectedFields, $prospectOnly, $type, $updatedAfter, $updatedBefore): Doctrine_Query
	{
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$type = ($type) ?: VisitorActivityConstants::getAllActivityTypes(true, array(VisitorActivityConstants::VISITOR));
		$query->andWhereIn('type', array_unique($type));

		$this->updatedAtHelper->applyDateRangeToQuery($query, $updatedAfter, $updatedBefore);

		if ($prospectOnly === true) {
			$query->addWhere('prospect_id IS NOT NULL');
		}

		return $query;
	}

	/**
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @param $prospectOnly
	 * @param $type
	 * @throws Exception
	 */
	public function validateWithArgs($prospectOnly, $type, $updatedAfter, $updatedBefore): void
	{
		parent::validateWithArgs($prospectOnly, $type, $updatedAfter, $updatedBefore);

		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);
		VisitorActivityTypeArgumentHelper::isValidVisitorActivityType($type);
	}
}

<?php
namespace Api\Config\Objects\VisitorActivity\ExportProcedures;

use Api\Config\Objects\VisitorActivity\Gen\ExportProcedures\AbstractVisitorActivityFilterByCreatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use DateTime;
use Doctrine_Query;
use Doctrine_Query_Exception;
use Exception;
use VisitorActivityConstants;

class VisitorActivityFilterByCreatedAtProcedure extends AbstractVisitorActivityFilterByCreatedAtProcedure
{
	private DateTimeArgumentHelper $createdAtHelper;

	/**
	 * VisitorActivityCreatedAtProcedure constructor.
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
	 * @param bool|null $prospectOnly
	 * @param array|null $type
	 * @return Doctrine_Query
	 * @throws Doctrine_Query_Exception
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		DateTime $createdAfter,
		?DateTime $createdBefore,
		?bool $prospectOnly,
		?array $type): Doctrine_Query
	{
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$type = ($type) ?: VisitorActivityConstants::getAllActivityTypes(true, array(VisitorActivityConstants::VISITOR));
		$query->andWhereIn('type', array_unique($type));

		$this->createdAtHelper->applyDateRangeToQuery($query, $createdAfter, $createdBefore);

		if ($prospectOnly === true) {
			$query->addWhere('prospect_id IS NOT NULL');
		}

		return $query;
	}

	/**
	 * @param DateTime $createdAfter
	 * @param DateTime|null $createdBefore
	 * @param bool|null $prospectOnly
	 * @param array|null $type
	 * @throws Exception
	 */
	public function validateWithArgs(
		DateTime $createdAfter,
		?DateTime $createdBefore,
		?bool $prospectOnly,
		?array $type
	): void {
		parent::validateWithArgs($createdAfter, $createdBefore, $prospectOnly, $type);

		$this->createdAtHelper->validateDateRange($createdAfter, $createdBefore);
		VisitorActivityTypeArgumentHelper::isValidVisitorActivityType($type);
	}
}

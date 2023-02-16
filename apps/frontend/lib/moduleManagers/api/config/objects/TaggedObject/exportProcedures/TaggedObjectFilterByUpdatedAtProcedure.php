<?php
namespace Api\Config\Objects\TaggedObject\ExportProcedures;

use Api\Config\Objects\TaggedObject\Gen\ExportProcedures\AbstractTaggedObjectFilterByUpdatedAtProcedure;
use Api\Export\ProcedureDefinition;
use Api\Export\Procedures\DateTimeArgumentHelper;
use Api\Objects\Query\QueryContext;
use Exception;
use DateTime;
use Doctrine_Query;

class TaggedObjectFilterByUpdatedAtProcedure extends AbstractTaggedObjectFilterByUpdatedAtProcedure
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
	 * @param array $selectedFields The fields that should be returned from this procedure.
	 * @param string $targetObjectType
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @return Doctrine_Query
	 */
	public function generateDoctrineQueryWithArgs(
		QueryContext $queryContext,
		array $selectedFields,
		string $targetObjectType,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): Doctrine_Query {
		$query = $this->getObjectDefinition()->createDoctrineQuery($queryContext, $selectedFields);

		$rootAlias = $query->getRootAlias();
		TagTypeArgumentHelper::applyTagTypeToQuery($query, $targetObjectType, $rootAlias);

		/** Add the related Tag to the query */
		$tagAlias = 'tagAlias';
		$query
			->innerJoin($rootAlias . '.piTag ' . $tagAlias)
			->andWhere($tagAlias . '.account_id = ?', $this->getAccountId());

		TagDateTimeArgumentHelper::applyTagDateTimeToQuery($query, $updatedBefore, $updatedAfter, $rootAlias, $tagAlias);

		return $query;
	}

	/**
	 * Validates the DateTime and tag type arguments.
	 * @param string $targetObjectType
	 * @param DateTime $updatedAfter
	 * @param DateTime|null $updatedBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
		string $targetObjectType,
		DateTime $updatedAfter,
		?DateTime $updatedBefore
	): void {
		parent::validateWithArgs($targetObjectType, $updatedAfter, $updatedBefore);
		$this->updatedAtHelper->validateDateRange($updatedAfter, $updatedBefore);
		TagTypeArgumentHelper::validateTagType($targetObjectType);
	}
}

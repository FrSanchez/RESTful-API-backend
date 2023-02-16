<?php
namespace Api\Objects\Query;

use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;

/**
 * Represents a query that can be made against objects in the object framework.
 *
 * @package Api\Objects\Query
 */
class ManyQuery extends AbstractQuery
{
	const LIMIT_DEFAULT = 200;

	const DIRECTION_ASC = OrderByPair::DIRECTION_ASC;
	const DIRECTION_DESC = OrderByPair::DIRECTION_DESC;

	/** @var int */
	private $limit = self::LIMIT_DEFAULT;

	/** @var int */
	private $offset = 0;

	/** @var OrderByPair[] */
	private $orderBy = [];

	/**
	 * Sets the number of results to be returned. By default, this set to 200, {@see LIMIT_DEFAULT}.
	 * @param int $limit
	 * @return $this
	 */
	public function withLimit(int $limit): self
	{
		$this->limit = $limit;
		return $this;
	}

	public function getLimit(): int
	{
		return $this->limit;
	}

	/**
	 * Replaces the current OrderBy clause with the specified OrderByPair instances.
	 * @param OrderByPair[] $orderByPairs
	 * @return $this
	 */
	public function withOrderBy(array $orderByPairs): self
	{
		$this->orderBy = $orderByPairs;
		return $this;
	}

	/**
	 * Adds a new order by clause to the query.
	 * @param FieldDefinition $fieldDefinition
	 * @param string $direction Direction of the ordering. See {@see DIRECTION_ASC} and {@see DIRECTION_DESC} for expected values.
	 * @return $this
	 */
	public function addOrderBy(FieldDefinition $fieldDefinition, string $direction = self::DIRECTION_ASC): self
	{
		$this->orderBy[] = new OrderByPair($fieldDefinition, $direction);
		return $this;
	}

	/**
	 * @return OrderByPair[]
	 */
	public function getOrderBy(): array
	{
		return $this->orderBy;
	}

	public function withOffset(int $offset): self
	{
		$this->offset = $offset;
		return $this;
	}

	public function getOffset(): int
	{
		return $this->offset;
	}

	public static function from(int $accountId, ObjectDefinition $objectDefinition, int $limit = self::LIMIT_DEFAULT): self
	{
		$query = new self($accountId, $objectDefinition);
		$query->limit = $limit;
		return $query;
	}
}

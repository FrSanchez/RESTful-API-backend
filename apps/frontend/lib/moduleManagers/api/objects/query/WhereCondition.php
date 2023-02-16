<?php

namespace Api\Objects\Query;

use Doctrine_Query;

/**
 * Represents a condition that can be applied to a query.
 *
 * Interface WhereCondition
 * @package Api\Objects\Query
 */
interface WhereCondition
{
	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery): void;
	public function toDql(): string;
	public function getParameters(): array;
}

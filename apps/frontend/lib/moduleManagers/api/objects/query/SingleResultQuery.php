<?php

namespace Api\Objects\Query;

use Api\Objects\ObjectDefinition;

/**
 * Represents a query that can be made against objects in the object framework.
 *
 * Class SingleResultQuery
 * @package Api\Objects\Query
 */
class SingleResultQuery extends AbstractQuery
{
	public static function from(int $accountId, ObjectDefinition $objectDefinition): self
	{
		return new self($accountId, $objectDefinition);
	}

	public final function getLimit(): int
	{
		return 1;
	}

	public final function getOffset(): int
	{
		return 0;
	}
}

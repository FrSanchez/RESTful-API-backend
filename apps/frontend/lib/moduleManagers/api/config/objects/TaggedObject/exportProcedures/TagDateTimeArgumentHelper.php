<?php
namespace Api\Config\Objects\TaggedObject\ExportProcedures;

use DateTime;
use dateTools;
use Doctrine_Query;

class TagDateTimeArgumentHelper
{
	/**
	 * Applies Tag Date Time Argument to the Query.
	 * @param Doctrine_Query $query
	 * @param DateTime|null $updatedBefore
	 * @param DateTime|null $updatedAfter
	 * @param string|null $tagObjectAlias,
	 * @param string|null $tagAlias,
	 */
	public static function applyTagDateTimeToQuery(Doctrine_Query $query, $updatedBefore, $updatedAfter, string $tagObjectAlias, string $tagAlias): void
	{
		$mysqlUpdatedAfter = dateTools::mysqlFormat($updatedAfter);
		$mysqlUpdatedBefore = dateTools::mysqlFormat($updatedBefore);

		// We care about both tag and tag object date ranges for these queries.
		// This way if either of them are updated, we include those records in our results.
		if ($updatedAfter && $updatedBefore) {
			$query->addWhere("($tagObjectAlias.created_at > ? AND $tagObjectAlias.created_at < ?) OR ($tagAlias.updated_at > ? AND $tagAlias.updated_at < ?)", [$mysqlUpdatedAfter, $mysqlUpdatedBefore, $mysqlUpdatedAfter, $mysqlUpdatedBefore]);
		}

		if ($updatedAfter && !$updatedBefore) {
			$query->addWhere("$tagObjectAlias.created_at > ? OR $tagAlias.updated_at > ?", [$mysqlUpdatedAfter, $mysqlUpdatedAfter]);
		}

		if (!$updatedAfter && $updatedBefore) {
			$query->addWhere("$tagObjectAlias.created_at < ? OR $tagAlias.updated_at < ?", [$mysqlUpdatedBefore, $mysqlUpdatedBefore]);
		}
	}
}

<?php
namespace Api\Config\Objects\VisitorActivity\Gen\ExportProcedures;

use Api\Export\Procedure;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
interface VisitorActivityFilterByUpdatedAtProcedureInterface extends Procedure
{
	const NAME = "filter_by_updated_at";

	const ARG_PROSPECT_ONLY = "prospect_only";
	const ARG_TYPE = "type";
	const ARG_UPDATED_AFTER = "updated_after";
	const ARG_UPDATED_BEFORE = "updated_before";
}

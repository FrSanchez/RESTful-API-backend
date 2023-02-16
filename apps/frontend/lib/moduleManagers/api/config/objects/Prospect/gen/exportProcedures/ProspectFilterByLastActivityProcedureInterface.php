<?php
namespace Api\Config\Objects\Prospect\Gen\ExportProcedures;

use Api\Export\Procedure;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
interface ProspectFilterByLastActivityProcedureInterface extends Procedure
{
	const NAME = "filter_by_last_activity";

	const ARG_DELETED = "deleted";
	const ARG_LAST_ACTIVITY_AFTER = "last_activity_after";
	const ARG_LAST_ACTIVITY_BEFORE = "last_activity_before";
}

<?php
namespace Api\Config\Objects\ListMembership\Gen\ExportProcedures;

use Api\Export\Procedure;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
interface ListMembershipFilterByUpdatedAtProcedureInterface extends Procedure
{
	const NAME = "filter_by_updated_at";

	const ARG_DELETED = "deleted";
	const ARG_UPDATED_AFTER = "updated_after";
	const ARG_UPDATED_BEFORE = "updated_before";
}

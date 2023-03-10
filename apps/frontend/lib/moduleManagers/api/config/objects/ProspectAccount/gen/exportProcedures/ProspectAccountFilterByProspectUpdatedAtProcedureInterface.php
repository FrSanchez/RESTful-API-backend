<?php
namespace Api\Config\Objects\ProspectAccount\Gen\ExportProcedures;

use Api\Export\Procedure;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
interface ProspectAccountFilterByProspectUpdatedAtProcedureInterface extends Procedure
{
	const NAME = "filter_by_prospect_updated_at";

	const ARG_PROSPECT_DELETED = "prospect_deleted";
	const ARG_PROSPECT_UPDATED_AFTER = "prospect_updated_after";
	const ARG_PROSPECT_UPDATED_BEFORE = "prospect_updated_before";
}

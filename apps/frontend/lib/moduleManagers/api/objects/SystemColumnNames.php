<?php
namespace Api\Objects;

/**
 * Column names used for common, system fields in many different tables.
 *
 * The column names below are the names of the columns in MySQL. For field names used in objects, {@see SystemFieldNames}.
 *
 * Class SystemColumns
 * @package Api\Objects
 */
class SystemColumnNames
{
	const CREATED_AT = 'created_at';
	const CREATED_BY = 'created_by';
	const EMAIL_TEMPLATE_ID = 'email_template_id';
	const ID = 'id';
	const IS_ARCHIVED = 'is_archived';
	const UPDATED_AT = 'updated_at';
	const UPDATED_BY = 'updated_by';
	const TRACKER_DOMAIN_ID = 'tracker_domain_id';
	const VANITY_URL_ID = 'vanity_url_id';
	const S3_KEY = 's3_key';
	const PROSPECT_CRM_LEAD_FID = 'crm_lead_fid';
	const PROSPECT_CRM_CONTACT_FID = 'crm_contact_fid';
	const ACCOUNT_ID = 'account_id';
	const PROSPECT_ACCOUNT_ID = 'prospect_account_id';
	const EXTERNAL_ACTIVITY_TYPE_FID = 'external_activity_type_fid';
}

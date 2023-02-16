<?php
namespace Api\Objects;

/**
 * Field names used for common, system fields in many different objects.
 *
 * The field names below are the names of the fields in Objects. For column names used in MySQL tables, {@see SystemColumnNames}.
 *
 * Class SystemFieldNames
 * @package Api\Objects
 */
class SystemFieldNames
{
	const CREATED_AT = 'createdAt';
	const CREATED_BY_ID = 'createdById';
	const ID = 'id';
	const IS_DELETED = 'isDeleted';
	const UPDATED_AT = 'updatedAt';
	const UPDATED_BY_ID = 'updatedById';
}

<?php
namespace Api\Objects\Query\Selections;

use Api\Objects\FieldDefinition;

/**
 * Represents a selection of a field on an object.
 */
interface FieldSelection
{
	/**
	 * @return FieldDefinition
	 */
	public function getFieldDefinition(): FieldDefinition;
}

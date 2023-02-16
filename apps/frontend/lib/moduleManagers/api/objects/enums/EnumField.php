<?php
namespace Api\Objects\Enums;

/**
 * For all fields in the object API that are Enums, a Enum Field class needs to be created and that class
 * must implement this EnumField interface.
 *
 * Interface EnumField
 * @package Api\Objects\Enums
 */
interface EnumField
{
	/**
	 * For each field, return the enums array. The array has to be in [integer => String] format
	 * @return array
	 */
	public function getArray(): array;
}

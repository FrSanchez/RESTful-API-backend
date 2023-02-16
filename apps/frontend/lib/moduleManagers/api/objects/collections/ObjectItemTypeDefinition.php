<?php
namespace Api\Objects\Collections;

/**
 * Collection itemType Definition that references an Object.
 */
class ObjectItemTypeDefinition implements ItemTypeDefinition
{
	private string $objectType;

	public function __construct(string $objectType)
	{
		$this->objectType = $objectType;
	}

	/**
	 * Gets the name of the object that's referenced.
	 * @return string
	 */
	public function getObjectType(): string
	{
		return $this->objectType;
	}
}

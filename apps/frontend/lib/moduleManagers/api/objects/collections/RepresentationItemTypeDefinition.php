<?php
namespace Api\Objects\Collections;

/**
 * Collection itemType Definition that references an Object.
 */
class RepresentationItemTypeDefinition implements ItemTypeDefinition
{
	private string $representationName;

	public function __construct(string $representationName)
	{
		$this->representationName = $representationName;
	}

	/**
	 * Gets the name of the representation that's referenced.
	 * @return string
	 */
	public function getRepresentationName(): string
	{
		return $this->representationName;
	}
}

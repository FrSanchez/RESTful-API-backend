<?php
namespace Api\Representations;

use Api\Objects\StaticObjectDefinition;

interface RepresentationDefinition
{
	/**
	 * @return string
	 */
	public function getName() : string;

	/**
	 * @return String[]
	 */
	public function getPropertyNames() : array;

	/**
	 * Gets the property with the given name. If the property is not within this representation, a null is returned.
	 * @param string $name
	 * @return RepresentationPropertyDefinition|null
	 */
	public function getPropertyByName(string $name) : ?RepresentationPropertyDefinition;

	/**
	 * Returns whether the object supports custom fields.
	 * @return bool
	 */
	public function supportsCustomFields() : bool;

	public function getStaticObjectDefinition(): ?StaticObjectDefinition;
}

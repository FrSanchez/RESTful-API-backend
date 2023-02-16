<?php
namespace Api\Representations;

use Api\Objects\StaticObjectDefinition;

/**
 * Simplified representation definition that is not account aware. This is used for ErrorRepresentation which
 * can be used before the account is known.
 */
class BasicRepresentationDefinitionImpl implements RepresentationDefinition
{
	private StaticRepresentationDefinition $staticRepresentationDefinition;

	/**
	 * RepresentationDefinition constructor.
	 * @param StaticRepresentationDefinition $staticRepresentationDefinition
	 */
	public function __construct(StaticRepresentationDefinition $staticRepresentationDefinition)
	{
		$this->staticRepresentationDefinition = $staticRepresentationDefinition;
	}

	/**
	 * @return string
	 */
	public function getName() : string
	{
		return $this->staticRepresentationDefinition->getName();
	}

	/**
	 * @return string[]
	 */
	public function getPropertyNames() : array
	{
		// Retrieving only the static properties. Ignoring the custom properties because this representation
		// doesn't have an account available (like ErrorRepresentation being used before account info is loaded).
		return $this->staticRepresentationDefinition->getPropertyNames();
	}

	/**
	 * Gets the property with the given name. If the property is not within this representation, a null is returned.
	 * @param string $name
	 * @return RepresentationPropertyDefinition|null
	 */
	public function getPropertyByName(string $name) : ?RepresentationPropertyDefinition
	{
		// Retrieving only the static properties. Ignoring the custom properties because this representation
		// doesn't have an account available (like ErrorRepresentation being used before account info is loaded).
		return $this->staticRepresentationDefinition->getPropertyByName($name);
	}

	/**
	 * Returns whether the object supports custom fields.
	 * @return bool
	 */
	public function supportsCustomFields() : bool
	{
		return $this->staticRepresentationDefinition->supportsCustomFields();
	}

	public function getStaticObjectDefinition(): ?StaticObjectDefinition
	{
		return $this->staticRepresentationDefinition->getStaticObjectDefinition();
	}
}

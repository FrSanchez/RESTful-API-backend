<?php

namespace Api\Representations;

use Api\DataTypes\PolymorphicDataType;
use Api\Objects\StaticObjectDefinition;

class StaticRepresentationDefinition
{
	/** @var string $name */
	private string $name;

	/** @var string[] $propertyNames */
	private array $propertyNames = [];

	/** @var RepresentationPropertyDefinition[] $lowercaseNamesToPropertiesMap */
	private array $lowercaseNamesToPropertiesMap = [];

	private CustomRepresentationPropertyProvider $customRepresentationPropertyProvider;

	private ?StaticObjectDefinition $staticObjectDefinition;
	private ?PolymorphicRepresentation $polymorphicDefinition;
	private bool $isPolymorphic;
	private array $polyProperties;
	private bool $suppressDescriptors;

	/**
	 * RepresentationDefinition constructor.
	 * @param string $name
	 * @param RepresentationPropertyDefinition[] $properties
	 * @param CustomRepresentationPropertyProvider $customRepresentationPropertyProvider
	 * @param StaticObjectDefinition|null $staticObjectDefinition
	 */
	public function __construct(
		string $name,
		array $properties,
		CustomRepresentationPropertyProvider $customRepresentationPropertyProvider,
		?StaticObjectDefinition $staticObjectDefinition = null,
		?bool $suppressDescriptors = false
	) {
		$this->name = $name;
		$this->isPolymorphic = false;
		$this->suppressDescriptors = $suppressDescriptors ?? false;

		$this->polyProperties = [];

		foreach ($properties as $propertyDefinition) {
			$this->propertyNames[] = $propertyDefinition->getName();
			$this->lowercaseNamesToPropertiesMap[strtolower($propertyDefinition->getName())] = $propertyDefinition;
			if ($propertyDefinition->getDataType() instanceof PolymorphicDataType) {
				$this->isPolymorphic = true;
				$this->polyProperties[$propertyDefinition->getName()] = $propertyDefinition->getDataType()->getRepresentation();
			}
		}

		$this->customRepresentationPropertyProvider = $customRepresentationPropertyProvider;
		$this->staticObjectDefinition = $staticObjectDefinition;
	}

	/**
	 * True to specify when this representation shouldn't generate documentation files - in OpenAPI, RAML, Postman and others
	 * @return bool
	 */
	public function isSuppressDescriptors() : bool
	{
		return $this->suppressDescriptors;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return String[]
	 */
	public function getPropertyNames(): array
	{
		return $this->propertyNames;
	}

	/**
	 * Gets the property with the given name. If the property is not within this representation, a null is returned.
	 * @param string $name
	 * @return RepresentationPropertyDefinition|null
	 */
	public function getPropertyByName(string $name): ?RepresentationPropertyDefinition
	{
		if (array_key_exists(strtolower($name), $this->lowercaseNamesToPropertiesMap)) {
			return $this->lowercaseNamesToPropertiesMap[strtolower($name)];
		}
		return null;
	}

	/**
	 * Returns whether the object supports custom fields.
	 * @return bool
	 */
	public function supportsCustomFields(): bool
	{
		return $this->staticObjectDefinition && $this->staticObjectDefinition->supportsCustomFields();
	}

	/**
	 * @return CustomRepresentationPropertyProvider
	 */
	public function getCustomRepresentationPropertyProvider(): CustomRepresentationPropertyProvider
	{
		return $this->customRepresentationPropertyProvider;
	}

	public function getStaticObjectDefinition(): ?StaticObjectDefinition
	{
		return $this->staticObjectDefinition;
	}
}

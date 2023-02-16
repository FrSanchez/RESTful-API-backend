<?php
namespace Api\Representations;

use AccountSettingsConstants;
use AccountSettingsManagerFactory;
use Api\Objects\StaticObjectDefinition;

/**
 * Standard representation definition used for the majority of representations in the system.
 */
class RepresentationDefinitionImpl implements RepresentationDefinition
{
	private int $version;
	private int $accountId;
	private StaticRepresentationDefinition $staticRepresentationDefinition;
	private AccountSettingsManagerFactory $accountSettingsManagerFactory;

	/**
	 * Lazy-Loaded within {@see ensureProperties}
	 * @var RepresentationPropertyDefinition[]|null
	 */
	private ?array $lowerPropertyNameToPropertyDefinitionMap = null;

	/**
	 * Lazy-Loaded within {@see ensureProperties}
	 * @var string[]|null
	 */
	private ?array $propertyNames = null;

	/**
	 * RepresentationDefinition constructor.
	 * @param StaticRepresentationDefinition $staticRepresentationDefinition
	 * @param int $version
	 * @param int $accountId
	 * @param AccountSettingsManagerFactory $accountSettingsManagerFactory
	 */
	public function __construct(
		StaticRepresentationDefinition $staticRepresentationDefinition,
		int $version,
		int $accountId,
		AccountSettingsManagerFactory $accountSettingsManagerFactory
	)
	{
		$this->staticRepresentationDefinition = $staticRepresentationDefinition;
		$this->version = $version;
		$this->accountId = $accountId;
		$this->accountSettingsManagerFactory = $accountSettingsManagerFactory;
	}

	/**
	 * @return string
	 */
	public function getName() : string
	{
		return $this->staticRepresentationDefinition->getName();
	}

	/**
	 * @return String[]
	 */
	public function getPropertyNames() : array
	{
		$this->ensureProperties();
		return $this->propertyNames;
	}

	/**
	 * Gets the property with the given name. If the property is not within this representation, a null is returned.
	 * @param string $name
	 * @return RepresentationPropertyDefinition|null
	 */
	public function getPropertyByName(string $name) : ?RepresentationPropertyDefinition
	{
		$this->ensureProperties();
		return $this->lowerPropertyNameToPropertyDefinitionMap[strtolower($name)] ?? null;
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

	private function ensureProperties(): void
	{
		if (!is_null($this->lowerPropertyNameToPropertyDefinitionMap)) {
			return;
		}

		// Properties for a representation consist of values from the static definition and the provider so combine them
		// together.

		$this->lowerPropertyNameToPropertyDefinitionMap = [];
		$this->propertyNames = [];

		// Add the properties defined in the static definition
		foreach ($this->staticRepresentationDefinition->getPropertyNames() as $propertyName) {
			$propertyDefinition = $this->staticRepresentationDefinition->getPropertyByName($propertyName);
			$this->propertyNames[] = $propertyDefinition->getName();
			$this->lowerPropertyNameToPropertyDefinitionMap[strtolower($propertyDefinition->getName())] = $propertyDefinition;
		}

		// Add the properties defined in the provider
		$providerProperties = $this->staticRepresentationDefinition
			->getCustomRepresentationPropertyProvider()
			->getAdditionalProperties($this->version, $this->accountId);
		foreach ($providerProperties as $propertyDefinition) {
			$this->propertyNames[] = $propertyDefinition->getName();
			$this->lowerPropertyNameToPropertyDefinitionMap[strtolower($propertyDefinition->getName())] = $propertyDefinition;
			}

	}
}

<?php
namespace Api\Objects\Collections;

use FeatureFlagAccessRule;

/**
 * Represents a definition of a collection within an ObjectDefinition
 */
class CollectionDefinition
{
	private int $version;
	private StaticCollectionDefinition $staticCollectionDefinition;

	/**
	 * @param int $version
	 * @param StaticCollectionDefinition $staticCollectionDefinition
	 */
	public function __construct(int $version, StaticCollectionDefinition $staticCollectionDefinition)
	{
		$this->version = $version;
		$this->staticCollectionDefinition = $staticCollectionDefinition;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->staticCollectionDefinition->getName();
	}

	/**
	 * @return ItemTypeDefinition
	 */
	public function getItemType(): ItemTypeDefinition
	{
		return $this->staticCollectionDefinition->getItemType();
	}

	/**
	 * @return string
	 */
	public function getBulkDataProcessorClass(): string
	{
		return $this->staticCollectionDefinition->getBulkDataProcessorClass();
	}

	/**
	 * @return FeatureFlagAccessRule
	 */
	public function getFeatureFlagAccessRules(): FeatureFlagAccessRule
	{
		return $this->staticCollectionDefinition->getFeatureFlagAccessRules();
	}
}

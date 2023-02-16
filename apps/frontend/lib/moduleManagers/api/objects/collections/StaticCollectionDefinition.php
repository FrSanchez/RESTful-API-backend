<?php
namespace Api\Objects\Collections;

use FeatureFlagAccessRule;

/**
 * Represents a static definition of a collection within an ObjectDefinition
 */
class StaticCollectionDefinition
{
	private string $name;
	private ItemTypeDefinition $itemType;
	private string $bulkDataProcessorClass;
	private FeatureFlagAccessRule $featureFlagAccessRule;

	public function __construct(
		string $name,
		ItemTypeDefinition $itemType,
		string $bulkDataProcessorClass,
		FeatureFlagAccessRule $featureFlagAccessRule
	) {
		$this->name = $name;
		$this->itemType = $itemType;
		$this->bulkDataProcessorClass = $bulkDataProcessorClass;
		$this->featureFlagAccessRule = $featureFlagAccessRule;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getItemType(): ItemTypeDefinition
	{
		return $this->itemType;
	}

	public function getBulkDataProcessorClass(): string
	{
		return $this->bulkDataProcessorClass;
	}

	public function getFeatureFlagAccessRules(): FeatureFlagAccessRule
	{
		return $this->featureFlagAccessRule;
	}
}

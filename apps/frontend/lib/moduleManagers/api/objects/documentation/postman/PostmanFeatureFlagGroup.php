<?php
namespace Api\Objects\Postman;

use FeatureFlagGroup;

class PostmanFeatureFlagGroup implements FeatureFlagGroup
{
	private array $enabledFeatureFlags;

	public function __construct(array $enabledFeatureFlags)
	{
		$this->enabledFeatureFlags = array_combine($enabledFeatureFlags, $enabledFeatureFlags);
	}

	/**
	 * Determines if the feature with the given key has been enabled.
	 * @param string $featureKey
	 * @return bool
	 */
	public function hasFeatureEnabled($featureKey): bool
	{
		return array_key_exists($featureKey, $this->enabledFeatureFlags);
	}
}

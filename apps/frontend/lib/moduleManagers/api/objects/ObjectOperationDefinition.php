<?php

namespace Api\Objects;

use AbilitiesAccessRule;
use FeatureFlagAccessRule;

class ObjectOperationDefinition
{
	/** @var string $name */
	private $name;
	/** @var FeatureFlagAccessRule $featureFlags */
	private $featureFlags;
	/** @var AbilitiesAccessRule $abilities */
	private $abilities;
	private bool $internalOnly;

	public function __construct(string $name, AbilitiesAccessRule $abilities, FeatureFlagAccessRule $featureFlag, bool $internalOnly)
	{
		$this->name = $name;
		$this->abilities = $abilities;
		$this->featureFlags = $featureFlag;
		$this->internalOnly = $internalOnly;
	}

	/**
	 * @return bool
	 */
	public function isInternalOnly(): bool
	{
		return $this->internalOnly;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return FeatureFlagAccessRule
	 */
	public function getFeatureFlags(): FeatureFlagAccessRule
	{
		return $this->featureFlags;
	}

	/**
	 * @return AbilitiesAccessRule
	 */
	public function getAbilities(): AbilitiesAccessRule
	{
		return $this->abilities;
	}
}

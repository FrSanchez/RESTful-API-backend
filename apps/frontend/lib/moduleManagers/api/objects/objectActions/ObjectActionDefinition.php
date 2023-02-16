<?php

namespace Api\Objects\ObjectActions;

use AbilitiesAccessRule;
use Api\Actions\ActionArgumentDefinition;
use Api\Actions\StaticActionDefinition;
use Exception;
use FeatureFlagAccessRule;

/**
 * Definition of an "object action".
 */
class ObjectActionDefinition
{
	private StaticActionDefinition $staticActionDefinition;
	private int $accountId;
	private int $version;

	/**
	 * ProcedureDefinition constructor.
	 * @param StaticActionDefinition $staticActionDefinition
	 * @param int $accountId
	 * @param int $version
	 */
	public function __construct(
		StaticActionDefinition $staticActionDefinition,
		int $accountId,
		int $version
	) {
		$this->staticActionDefinition = $staticActionDefinition;
		$this->accountId = $accountId;
		$this->version = $version;
	}

	/**
	 * Gets the name of this object action.
	 * @return string
	 */
	public function getName(): string
	{
		return $this->staticActionDefinition->getName();
	}

	/**
	 * Gets the name of the class that handles the objects action's logic when it is executed.
	 * @return string
	 */
	public function getActionClassName(): string
	{
		return $this->staticActionDefinition->getActionClassName();
	}

	/**
	 * Gets the object that this object action is associated to.
	 * @return int
	 */
	public function getObject(): int
	{
		return $this->staticActionDefinition->getObject();
	}

	/**
	 * Determines if the object action has an argument with the given name. This match is case-insensitive.
	 * @param string $argName
	 * @return bool
	 */
	public function hasArgumentByName(string $argName): bool
	{
		return $this->staticActionDefinition->hasArgumentByName($argName, $this->version);
	}

	/**
	 * Gets the definition of the argument with the given name or null when none match. This name matching is case-insensitive.
	 * @param string $argName
	 * @return ActionArgumentDefinition|null
	 */
	public function getArgumentByName(string $argName): ?ActionArgumentDefinition
	{
		return $this->staticActionDefinition->getArgumentByName($argName, $this->version);
	}

	/**
	 * Gets the name of all the arguments associated to this action.
	 * @return string[]
	 */
	public function getArgumentNames(): array
	{
		return $this->staticActionDefinition->getArgumentNames($this->version);
	}

	/**
	 * Gets an array of the argument names for arguments where required is True.
	 * @return string[]
	 */
	public function getRequiredArgumentNames(): array
	{
		return $this->staticActionDefinition->getRequiredArgumentNames($this->version);
	}

	/**
	 * Gets a count of all the arguments.
	 * @return int
	 */
	public function getArgumentCount(): int
	{
		return $this->staticActionDefinition->getArgumentCount();
	}

	/**
	 * Gets the required abilities for this object action.
	 * @return AbilitiesAccessRule
	 */
	public function getRequiredAbilities(): AbilitiesAccessRule
	{
		return $this->staticActionDefinition->getRequiredAbilities();
	}

	/**
	 * Gets the required feature flags for this object action.
	 * @return FeatureFlagAccessRule
	 */
	public function getRequiredFeatureFlags(): FeatureFlagAccessRule
	{
		return $this->staticActionDefinition->getRequiredFeatureFlags();
	}

	/**
	 * Creates a new instance of the object action handler.
	 * @return ObjectAction
	 * @throws Exception
	 */
	public function createObjectAction(): ObjectAction
	{
		$objectActionClassName = $this->staticActionDefinition->getActionClassName();
		return new $objectActionClassName();
	}

	/**
	 * @return bool
	 */
	public function isResponseRepresentationReturned(): bool
	{
		return $this->staticActionDefinition->isResponseRepresentationReturned();
	}

	/**
	 * @return string
	 */
	public function getResponseRepresentationName(): string
	{
		return $this->staticActionDefinition->getResponseRepresentationName();
	}
}

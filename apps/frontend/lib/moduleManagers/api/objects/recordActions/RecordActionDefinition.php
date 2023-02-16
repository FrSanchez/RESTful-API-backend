<?php
namespace Api\Objects\RecordActions;

use AbilitiesAccessRule;
use Api\Actions\ActionArgumentDefinition;
use Api\Actions\ActionDefinition;
use Api\Actions\StaticActionDefinition;
use Api\Objects\ObjectDefinition;
use FeatureFlagAccessRule;
use Exception;

/**
 * Definition of a Record Action.
 *
 * Class ProcedureDefinition
 * @package Api\Export
 */
class RecordActionDefinition implements ActionDefinition
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
	 * @return string
	 */
	public function getName(): string
	{
		return $this->staticActionDefinition->getName();
	}

	/**
	 * @inheritDoc
	 */
	public function getNameVersioned(int $version) : string
	{
		return $this->staticActionDefinition->getNameVersioned($version);
	}

	/**
	 * @return string
	 */
	public function getActionClassName(): string
	{
		return $this->staticActionDefinition->getActionClassName();
	}

	/**
	 * Gets the object that this action is associated to.
	 * @return int
	 */
	public function getObject(): int
	{
		return $this->staticActionDefinition->getObject();
	}

	/**
	 * @param string $argName
	 * @return bool
	 */
	public function hasArgumentByName(string $argName): bool
	{
		return $this->staticActionDefinition->hasArgumentByName($argName, $this->version);
	}

	/**
	 * @param string $argName
	 * @return ActionArgumentDefinition|null
	 */
	public function getArgumentByName(string $argName): ?ActionArgumentDefinition
	{
		return $this->staticActionDefinition->getArgumentByName($argName, $this->version);
	}

	/**
	 * @inheritDoc
	 */
	public function getArgumentByPreV5Name(string $argName): ?ActionArgumentDefinition
	{
		return $this->staticActionDefinition->getArgumentByName($argName, 3);
	}

	/**
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
	 * Gets the AbilitiesAccessRule which contains all required abilities for the procedure
	 * @return AbilitiesAccessRule
	 */
	public function getRequiredAbilities(): AbilitiesAccessRule
	{
		return $this->staticActionDefinition->getRequiredAbilities();
	}

	/**
	 * Gets the required feature flags for this procedure.
	 * @return FeatureFlagAccessRule
	 */
	public function getRequiredFeatureFlags(): FeatureFlagAccessRule
	{
		return $this->staticActionDefinition->getRequiredFeatureFlags();
	}

	/**
	 * Creates a new instance of the class associated to the record action
	 * @return RecordAction
	 * @throws Exception
	 */
	public function createRecordAction(): RecordAction
	{
		$recordActionClassName = $this->staticActionDefinition->getActionClassName();
		return new $recordActionClassName();
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

	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * @inheritDoc
	 */
	public function isInternalOnly(): bool
	{
		return $this->staticActionDefinition->isInternalOnly();
	}

}

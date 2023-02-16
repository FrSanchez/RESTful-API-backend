<?php
namespace Api\Objects\Relationships;

use FeatureFlagAccessRule;
use AlwaysTrueFeatureFlagAccessRule;

class RelationshipDefinition
{
	/** @var string $name */
	private $name;

	/** @var string $doctrineName */
	private $doctrineName;

	/** @var string|null $bulkDataProcessorClass */
	private $bulkDataProcessorClass;

	/** @var RelationshipReferenceToDefinition $relationReferenceDefinition */
	private $relationReferenceDefinition;

	/** @var FeatureFlagAccessRule $featureFlagAccessRule */
	private $featureFlagAccessRule;

	public function __construct(
		string $name,
		string $doctrineName,
		?string $bulkDataProcessorClass,
		RelationshipReferenceToDefinition $relationReferenceDefinition,
		FeatureFlagAccessRule $featureFlagAccessRule = null
	) {
		$this->name = $name;
		$this->doctrineName = $doctrineName;
		$this->bulkDataProcessorClass = $bulkDataProcessorClass;
		$this->relationReferenceDefinition = $relationReferenceDefinition;
		$this->featureFlagAccessRule = $featureFlagAccessRule ?? AlwaysTrueFeatureFlagAccessRule::getInstance();
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getDoctrineName(): string
	{
		return $this->doctrineName;
	}

	/**
	 * @return string|null
	 */
	public function getBulkDataProcessorClass(): ?string
	{
		return $this->bulkDataProcessorClass;
	}

	public function isBulkRelationship(): bool
	{
		return !is_null($this->bulkDataProcessorClass);
	}

	/**
	 * @return RelationshipReferenceToDefinition
	 */
	public function getReferenceToDefinition(): RelationshipReferenceToDefinition
	{
		return $this->relationReferenceDefinition;
	}

	/**
	 * @return FeatureFlagAccessRule
	 */
	public function getFeatureFlagAccessRule(): FeatureFlagAccessRule
	{
		return $this->featureFlagAccessRule;
	}
}

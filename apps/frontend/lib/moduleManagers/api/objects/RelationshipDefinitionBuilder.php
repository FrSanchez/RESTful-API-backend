<?php
namespace Api\Objects;

use Api\Objects\Relationships\RelationshipDefinition;
use Api\Objects\Relationships\RelationshipReferenceToDefinition;
use FeatureFlagAccessRule;

/**
 * Builder for a RelationshipDefinition instance. See RelationshipDefinition description for more information.
 *
 * Class RelationshipDefinitionBuilder
 * @package Api\Objects
 * @see RelationshipDefinition
 */

class RelationshipDefinitionBuilder
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

	/**
	 * @param string $name
	 * @return $this
	 */
	public function withName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * @param string $doctrineName
	 * @return $this
	 */
	public function withDoctrineName(string $doctrineName): self
	{
		$this->doctrineName = $doctrineName;
		return $this;
	}

	/**
	 * @param string|null $bulkDataProcessorClass
	 * @return $this
	 */
	public function withBulkDataProcessor(?string $bulkDataProcessorClass): self
	{
		$this->bulkDataProcessorClass = $bulkDataProcessorClass;
		return $this;
	}

	/**
	 * @param RelationshipReferenceToDefinition $relationshipReferenceToDefinition
	 * @return $this
	 */
	public function withRelationshipReferenceToDefinition(
		RelationshipReferenceToDefinition $relationshipReferenceToDefinition
	): self {
		$this->relationReferenceDefinition = $relationshipReferenceToDefinition;
		return $this;
	}

	/**
	 * @param FeatureFlagAccessRule $featureFlagAccessRule
	 * @return $this
	 */
	public function withFeatureFlagAccessRule(FeatureFlagAccessRule $featureFlagAccessRule): self
	{
		$this->featureFlagAccessRule = $featureFlagAccessRule;
		return $this;
	}

	/**
	 * @return RelationshipDefinition
	 */
	public function build(): RelationshipDefinition
	{
		return new RelationshipDefinition(
			$this->name,
			$this->doctrineName,
			$this->bulkDataProcessorClass,
			$this->relationReferenceDefinition,
			$this->featureFlagAccessRule
		);
	}

	/**
	 * @return RelationshipDefinitionBuilder
	 */
	public static function create(): RelationshipDefinitionBuilder
	{
		return new self();
	}

}

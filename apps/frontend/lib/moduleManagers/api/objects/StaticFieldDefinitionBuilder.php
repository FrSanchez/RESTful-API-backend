<?php
namespace Api\Objects;

use Api\DataTypes\DataType;
use FeatureFlagAccessRule;
use RuntimeException;
use AlwaysTrueFeatureFlagAccessRule;

/**
 * Builder for a StaticFieldDefinition instance. See StaticFieldDefinition description for more information.
 *
 * Class StaticFieldDefinitionBuilder
 * @package Api\Objects
 * @see StaticFieldDefinition
 */
class StaticFieldDefinitionBuilder
{
	private string $name;
	private DataType $dataType;
	private ?string $doctrineField = null;
	private bool $derived = false;
	private bool $custom = false;
	private ?string $preVersion5Name = null;
	private bool $preVersion5ExportDefault = true;
	private bool $filterable = false;
	private ?bool $filterableByRange = null;
	private bool $required = false;
	private bool $readOnly = false;
	private bool $writeOnly = false;
	private bool $nullable = false;
	private bool $sortable = false;
	private bool $queryable = true;
	private ?string $bulkDataProcessorClass = null;
	private ?FeatureFlagAccessRule $featureFlagAccessRule = null;

	/**
	 * @param string $name
	 * @param string|null $preVersion5Name If not specified, the name value is derived from the name field.
	 * @return self
	 */
	public function withName(string $name, ?string $preVersion5Name = null): self
	{
		$this->name = $name;
		$this->preVersion5Name = $preVersion5Name;
		return $this;
	}

	public function withDataType(DataType $dataType): self
	{
		$this->dataType = $dataType;
		return $this;
	}

	public function withDoctrineField(?string $doctrineField): self
	{
		$this->doctrineField = $doctrineField;
		return $this;
	}

	public function withDerived(bool $derived): self
	{
		$this->derived = $derived;
		return $this;
	}

	public function withCustom(bool $custom): self
	{
		$this->custom = $custom;
		return $this;
	}

	public function withFilterable(bool $filterable): self
	{
		$this->filterable = $filterable;
		return $this;
	}

	public function withFilterableByRange(?bool $filterableByRange): self
	{
		if ($this->filterable === true && !is_null($filterableByRange)) {
			$this->filterableByRange = $filterableByRange;
		}
		return $this;
	}

	public function withRequired(bool $required): self
	{
		$this->required = $required;
		return $this;
	}

	public function withReadOnly(bool $readOnly): self
	{
		$this->readOnly = $readOnly;
		return $this;
	}

	public function withWriteOnly(bool $writeOnly): self
	{
		$this->writeOnly = $writeOnly;
		return $this;
	}

	public function withSortable(bool $sortable): self
	{
		$this->sortable = $sortable;
		return $this;
	}

	/**
	 * @param bool $nullable
	 * @return self
	 * @see FieldDefinition::isNullable()
	 */
	public function withNullable(bool $nullable): self
	{
		$this->nullable = $nullable;
		return $this;
	}


	public function withBulkDataProcessorClass(?string $bulkDataProcessorClass): self
	{
		$this->bulkDataProcessorClass = $bulkDataProcessorClass;
		return $this;
	}

	/**
	 * @param bool $preVersion5ExportDefault
	 * @return self
	 * @see FieldDefinition::isFieldIncludedInExportDefault()
	 */
	public function withPreVersion5ExportDefault(bool $preVersion5ExportDefault): self
	{
		$this->preVersion5ExportDefault = $preVersion5ExportDefault;
		return $this;
	}

	public function withFeatureFlagAccessRule(FeatureFlagAccessRule $featureFlagAccessRule): self
	{
		$this->featureFlagAccessRule = $featureFlagAccessRule;
		return $this;
	}

	public function withQueryable(bool $queryable): self
	{
		$this->queryable = $queryable;
		return $this;
	}

	public function build(): StaticFieldDefinition
	{
		if (is_null($this->dataType)) {
			throw new RuntimeException("DataType must be specified for a field definition.");
		}

		return new StaticFieldDefinition(
			$this->name,
			$this->dataType,
			$this->doctrineField,
			$this->derived,
			$this->custom,
			$this->required,
			$this->readOnly,
			$this->sortable,
			$this->preVersion5Name,
			$this->preVersion5ExportDefault,
			$this->nullable,
			$this->bulkDataProcessorClass,
			$this->featureFlagAccessRule,
			$this->filterable,
			$this->writeOnly,
			$this->queryable,
			$this->filterableByRange
		);
	}

	public static function create(): StaticFieldDefinitionBuilder
	{
		return new self();
	}
}

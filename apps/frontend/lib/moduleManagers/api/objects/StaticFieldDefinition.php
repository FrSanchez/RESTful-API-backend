<?php
namespace Api\Objects;

use AlwaysTrueFeatureFlagAccessRule;
use FeatureFlagAccessRule;
use Api\DataTypes\DataType;
use RuntimeException;
use stringTools;

/**
 * Definition of a field in the an object and is used to retrieve type information of an object.
 *
 * A "field" is a specialization of property within the API that is usually backed by a column in a database table. All
 * fields are properties in the API but not all properties in the API are fields.
 *
 * Class StaticFieldDefinition
 * @package Api\Objects
 * @see StaticFieldDefinitionBuilder
 */
class StaticFieldDefinition
{
	private string $name;
	private DataType $dataType;
	private string $doctrineField;
	private bool $derived;
	private bool $custom;
	private string $preVersion5Name;
	private bool $preVersionExportDefault;
	private bool $filterable;
	private ?bool $filterableByRange;
	private bool $required;
	private bool $readOnly;
	private bool $writeOnly;
	private bool $nullable;
	private bool $sortable;
	private bool $queryable;
	private ?string $bulkDataProcessorClass;
	private FeatureFlagAccessRule $featureFlagAccessRule;

	/**
	 * @param string $name The API name of the field. camelCase.
	 * @param DataType $dataType The data type of the field.
	 * @param string|null $doctrineField The field within Doctrine. If null is specified, then the API name is used when
	 * querying for the field in Doctrine (API name === field in Doctrine).
	 * @param bool $derived True if this field is calculated/modified based by PHP code.
	 * @param bool $custom True if this field is a custom field (dynamically added, non-standard).
	 * @param bool $required True if the field is required when creating this object.
	 * @param bool $readOnly True if this field is only populated during a read.
	 * @param bool $sortable True if the field is allowed to be sorted in query operations. This should only be enabled
	 * for fields that have proper DB indexes.
	 * @param string|null $namePreVersion5 Specifies the name of the field for version 4 or lower consumers. If not specified or null,
	 * the value will be calculated based on the fields above.
	 * @param bool $exportDefaultPreVersion5 Specifies if the field should be automatically returned in export v3/v4.
	 * There are some fields that are more expensive to calculate and this would allow the default list to not include
	 * them. The user can still request the field using the "fields" property.
	 * @param bool $nullable True if this field allows null as a valid value. Default is false.
	 * @param string|null $bulkDataProcessorClass Specifies the bulk field processor used for this field.
	 * @param FeatureFlagAccessRule|null $featureFlagAccessRule optional feature flag access rule that is used to gate access to this field
	 * @param bool $filterable True if the field allows filtering.
	 * @param bool $writeOnly
	 * @param bool $queryable
	 * @param bool|null $filterableByRange
	 */
	public function __construct(
		string $name,
		DataType $dataType,
		?string $doctrineField = null,
		bool $derived = false,
		bool $custom = false,
		bool $required = false,
		bool $readOnly = false,
		bool $sortable = false,
		string $namePreVersion5 = null,
		bool $exportDefaultPreVersion5 = true,
		bool $nullable = false,
		?string $bulkDataProcessorClass = null,
		FeatureFlagAccessRule $featureFlagAccessRule = null,
		bool $filterable = false,
		bool $writeOnly = false,
		bool $queryable = true,
		?bool $filterableByRange = null
	) {
		$this->name = $name;
		$this->dataType = $dataType;
		$this->doctrineField = $doctrineField ?? stringTools::snakeFromCamelCase($name);
		$this->derived = $derived;
		$this->custom = $custom;
		$this->filterable = $filterable;
		$this->filterableByRange = $filterableByRange;
		$this->required = $required;
		$this->readOnly = $readOnly;
		$this->writeOnly = $writeOnly;
		$this->nullable = $nullable;
		$this->sortable = $sortable;
		$this->queryable = $queryable;
		$this->preVersionExportDefault = $exportDefaultPreVersion5;
		$this->featureFlagAccessRule = $featureFlagAccessRule ?? AlwaysTrueFeatureFlagAccessRule::getInstance();
		$this->bulkDataProcessorClass = $bulkDataProcessorClass;

		// For <=4, the field name should be snake case. Use override or derive snake case from name.
		$this->preVersion5Name = $namePreVersion5 ?? stringTools::snakeFromCamelCase($name);
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Gets the name of the field based upon the version.
	 * @param int $version The version number to retrieve the name for. Normally this corresponds to the version value in the request.
	 * @return string
	 */
	public function getNameVersioned(int $version): string
	{
		if ($version <= 4) {
			return $this->preVersion5Name;
		}

		return $this->name;
	}

	/**
	 * Gets the data type of this field.
	 * @return DataType
	 */
	public function getDataType(): DataType
	{
		return $this->dataType;
	}

	/**
	 * @return string
	 */
	public function getDoctrineField(): string
	{
		return $this->doctrineField;
	}

	/**
	 * Returns true when this field is calculated/modified based on PHP code.
	 * @return bool
	 */
	public function isDerived(): bool
	{
		return $this->derived;
	}

	/**
	 * Indicates whether or not a given field is standard or custom (static vs dynamic)
	 * @return bool
	 */
	public function isCustom(): bool
	{
		return $this->custom;
	}

	/**
	 * Returns true when the field is read only while creating or updating.
	 * @return bool
	 */
	public function isReadOnly(): bool
	{
		return $this->readOnly;
	}

	/**
	 * Returns true when the field is write only while reading or querying.
	 * @return bool
	 */
	public function isWriteOnly(): bool
	{
		return $this->writeOnly;
	}

	/**
	 * Returns true if the field is required when creating an new value.
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->required;
	}

	/**
	 * Returns true if the field can be filtered in a query. This is usually only available on fields that are part of
	 * an index.
	 * @return bool
	 */
	public function isFilterable(): bool
	{
		return $this->filterable;
	}

	/**
	 * Returns true if the field is filterable and can be filtered by range in a query. This is usually only available on fields that are part of
	 * an index.
	 * @return bool
	 */
	public function isFilterableByRange(): bool
	{
		if ($this->isFilterable()) {
			return $this->filterableByRange;
		}
		return false;
	}

	/**
	 * Returns true if the field can be passed in the order clause when returning multiple results. This is usually
	 * available on fields that are part of an index.
	 * @return bool
	 */
	public function isSortable(): bool
	{
		return $this->sortable;
	}

	/**
	 * Returns true if the field allows NULL as an allowed value during create and update and can also return NULL
	 * values.
	 * @return bool
	 */
	public function isNullable(): bool
	{
		return $this->nullable;
	}

	/**
	 * Return true if the field value is loaded using a bulk processor.
	 * @return bool
	 */
	public function isBulkField()
	{
		return !is_null($this->bulkDataProcessorClass);
	}

	/**
	 * @return string|null
	 */
	public function getBulkDataProcessorClass(): ?string
	{
		return $this->bulkDataProcessorClass;
	}

	/**
	 * @return FeatureFlagAccessRule
	 */
	public function getFeatureFlagAccessRule(): FeatureFlagAccessRule
	{
		return $this->featureFlagAccessRule;
	}

	/**
	 * Returns true if the field should be included in export's default field list.
	 * @param int $version
	 * @return bool
	 */
	public function isFieldIncludedInExportDefault(int $version): bool
	{
		if ($version <= 4) {
			return $this->preVersionExportDefault;
		}
		throw new RuntimeException('Export should require fields in v5 instead of relying upon default fields.');
	}

	/**
	 * Is the field available on query.
	 * @return bool
	 */
	public function isQueryable(): bool
	{
		return $this->queryable;
	}
}

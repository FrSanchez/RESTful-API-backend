<?php
namespace Api\Objects;

use AlwaysTrueFeatureFlagAccessRule;
use Api\Representations\DerivedRepresentationTransformer;
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
 * Class FieldDefinition
 * @package Api\Objects
 * @see StaticFieldDefinitionBuilder
 */
class FieldDefinition
{
	private int $version;
	private StaticFieldDefinition $staticFieldDefinition;

	/**
	 * @param int $version
	 * @param StaticFieldDefinition $staticFieldDefinition
	 */
	public function __construct(int $version, StaticFieldDefinition $staticFieldDefinition)
	{
		$this->version = $version;
		$this->staticFieldDefinition = $staticFieldDefinition;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->getNameVersioned($this->version);
	}

	/**
	 * @param int $version
	 * @return string
	 */
	public function getNameVersioned(int $version) : string
	{
		return $this->staticFieldDefinition->getNameVersioned($version);
	}

	/**
	 * Gets the data type of this field.
	 * @return DataType
	 */
	public function getDataType(): DataType
	{
		return $this->staticFieldDefinition->getDataType();
	}

	/**
	 * @return string
	 */
	public function getDoctrineField(): string
	{
		return $this->staticFieldDefinition->getDoctrineField();
	}

	/**
	 * Returns true when this field is calculated/modified based on PHP code.
	 * @return bool
	 */
	public function isDerived(): bool
	{
		return $this->staticFieldDefinition->isDerived();
	}

	/**
	 * Indicates whether or not a given field is standard or custom (static vs dynamic)
	 * @return bool
	 */
	public function isCustom(): bool
	{
		return $this->staticFieldDefinition->isCustom();
	}

	/**
	 * Returns true when the field is read only while creating or updating.
	 * @return bool
	 */
	public function isReadOnly(): bool
	{
		return $this->staticFieldDefinition->isReadOnly();
	}

	/**
	 * Returns true when the field is write only while querying or reading.
	 * @return bool
	 */
	public function isWriteOnly(): bool
	{
		return $this->staticFieldDefinition->isWriteOnly();
	}

	/**
	 * Returns true if the field is required when creating an new value.
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->staticFieldDefinition->isRequired();
	}

	/**
	 * Returns true if the field can be filtered in a query. This is usually only available on fields that are part of
	 * an index.
	 * @return bool
	 */
	public function isFilterable(): bool
	{
		return $this->staticFieldDefinition->isFilterable();
	}

	/**
	 * Returns true if the field is filterable and can be filtered by range in a query. This is usually only available on fields that are part of
	 * an index.
	 * @return bool
	 */
	public function isFilterableByRange(): bool
	{
		return $this->staticFieldDefinition->isFilterableByRange();
	}

	/**
	 * Returns true if the field can be passed in the order clause when returning multiple results. This is usually
	 * available on fields that are part of an index.
	 * @return bool
	 */
	public function isSortable(): bool
	{
		return $this->staticFieldDefinition->isSortable();
	}

	/**
	 * Returns true if the field allows NULL as an allowed value during create and update and can also return NULL
	 * values.
	 * @return bool
	 */
	public function isNullable(): bool
	{
		return $this->staticFieldDefinition->isNullable();
	}

	/**
	 * Return true if the field value is loaded using a bulk processor.
	 * @return bool
	 */
	public function isBulkField()
	{
		return $this->staticFieldDefinition->isBulkField();
	}

	/**
	 * @return string|null
	 */
	public function getBulkDataProcessorClass(): ?string
	{
		return $this->staticFieldDefinition->getBulkDataProcessorClass();
	}

	/**
	 * @return FeatureFlagAccessRule
	 */
	public function getFeatureFlagAccessRule(): FeatureFlagAccessRule
	{
		return $this->staticFieldDefinition->getFeatureFlagAccessRule();
	}

	/**
	 * Returns true if the field should be included in export's default field list.
	 * @return bool
	 */
	public function isFieldIncludedInExportDefault(): bool
	{
		return $this->staticFieldDefinition->isFieldIncludedInExportDefault($this->version);
	}

	/**
	 * Is the field available on query.
	 * @return bool
	 */
	public function isQueryable(): bool
	{
		return $this->staticFieldDefinition->isQueryable();
	}

	/**
	 * @return string
	 */
	public function getPreV5Name(): string
	{
		return $this->staticFieldDefinition->getNameVersioned(4);
	}
}

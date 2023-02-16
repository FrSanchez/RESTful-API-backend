<?php
namespace Api\DataTypes;

use arrayTools;
use RuntimeException;
use TypedXMLOrJSONWriter;

/**
 * "One of" data type that can either be a primitive or an array of the same primitive type, e.g. oneOf(int, int[]).
 * Due to PHP's and MySQL's flexible type systems, it's difficult to support oneOf at a generic level.
 */
class OneOfPrimitiveOrArrayOfPrimitiveDataType implements DataType
{
	private DataType $dataType;
	private ArrayDataType $arrayDataType;

	public function __construct(DataType $dataType)
	{
		if ($dataType instanceof ArrayDataType) {
			throw new RuntimeException("Array of arrays is not supported.");
		}

		$this->dataType = $dataType;
		$this->arrayDataType = new ArrayDataType($dataType, 0);
	}

	public function getName(): string
	{
		return $this->dataType->getName() . " or " . $this->arrayDataType->getName();
	}

	public function validateParameterValue(string $userValue, ConversionContext $context): array
	{
		list($isArrayValid, $arrayValidationError) = $this->arrayDataType->validateParameterValue($userValue, $context);
		if ($isArrayValid) {
			return [true, null];
		}

		list($isPrimitiveValid, $primitiveValidationError) = $this->dataType->validateParameterValue($userValue, $context);
		if ($isPrimitiveValid) {
			return [true, null];
		}

		$name = $this->dataType->getName();
		return [false, "Expected value to be one of the following types: {$name}, {$name}[]"];
	}

	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		list($isArrayValid, $arrayValidationError) = $this->arrayDataType->validateParameterValue($userValue, $context);
		if ($isArrayValid) {
			// There's a weird corner case here due to arrays being represented as CSV in parameters.
			// If the value is a single value (aka no comma present), then we always return the single value directly
			// and NEVER an array containing a single value.
			$values = $this->arrayDataType->convertParameterValueToServerValue($userValue, $context);
			if (is_array($values) && count($values) == 1) {
				return $values[0];
			}
			return $values;
		}

		list($isPrimitiveValid, $primitiveValidationError) = $this->dataType->validateParameterValue($userValue, $context);
		if ($isPrimitiveValid) {
			return $this->dataType->convertParameterValueToServerValue($userValue, $context);
		}

		$name = $this->dataType->getName();
		throw new DataTypeConversionException("Unable to convert the " . gettype($userValue) . " value to one of the following types: {$name}, {$name}[]");
	}

	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		list($isArrayValid, $arrayValidationError) = $this->arrayDataType->validateJsonValue($userValue, $context);
		if ($isArrayValid) {
			return [true, null];
		}

		list($isPrimitiveValid, $primitiveValidationError) = $this->dataType->validateJsonValue($userValue, $context);
		if ($isPrimitiveValid) {
			return [true, null];
		}

		$name = $this->dataType->getName();
		return [false, "Expected value to be one of the following types: {$name}, {$name}[]"];
	}

	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($userValue)) {
			return $this->arrayDataType->convertJsonValueToServerValue($userValue, $context);
		}

		return $this->dataType->convertJsonValueToServerValue($userValue, $context);
	}

	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($dbValue)) {
			return $this->arrayDataType->convertDatabaseValueToApiValue($dbValue, $context);
		}

		return $this->dataType->convertDatabaseValueToApiValue($dbValue, $context);
	}

	public function convertDatabaseValueToServerValue($dbValue)
	{
		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($dbValue)) {
			return $this->arrayDataType->convertDatabaseValueToServerValue($dbValue);
		}

		return $this->dataType->convertDatabaseValueToServerValue($dbValue);
	}

	public function isServerValueType($value): bool
	{
		if (is_null($value)) {
			return false;
		}

		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($value)) {
			return $this->arrayDataType->isServerValueType($value);
		}
		return $this->dataType->isServerValueType($value);
	}

	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($serverValue)) {
			return $this->arrayDataType->convertServerValueToApiValue($serverValue, $context);
		}
		return $this->dataType->convertServerValueToApiValue($serverValue, $context);
	}

	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		// Make the assumption that an array should be converted using the array data type. This means we can't
		// support array of arrays at this time since it will always be detected here as an array.
		if ($this->isIndexedArray($serverValue)) {
			$this->arrayDataType->writeServerValueToXmlWriter($writer, $context, $propertyName, $serverValue);
		} else {
			$this->dataType->writeServerValueToXmlWriter($writer, $context, $propertyName, $serverValue);
		}
	}

	private function isIndexedArray($value): bool
	{
		if (is_array($value)) {
			if (empty($value)) {
				// PHP weirdness! Since PHP code uses associative array for objects and indexes arrays for arrays,
				// there's no way to determine if the value specified is an empty object or empty array.
				// For now just fail and revisit if this scenario is ever encountered.
				throw new RuntimeException("Unable to determine if empty object or empty array. See notes in code around this exception.");

			}

			if (arrayTools::isIndexedArray($value)) {
				return true;
			} else {
				// Value was sent as an associative array, like ["foo" => "bar"], so it's fall to the regular data type.
				return false;
			}
		}
		return false;
	}
}

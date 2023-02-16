<?php
namespace Api\DataTypes;

use Api\Serialization\SerializationException;
use arrayTools;
use csvIterator;
use RuntimeException;
use TypedXMLOrJSONWriter;

class ArrayDataType implements DataType
{
	const NAME = "array";

	private $itemDataType;
	private $minItems;

	public function __construct(DataType $itemDataType, int $minItems)
	{
		$this->itemDataType = $itemDataType;
		$this->minItems = $minItems;
	}

	/**
	 * Gets the name of the data type.
	 * @return string
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * @return DataType
	 */
	public function getItemDataType(): DataType
	{
		return $this->itemDataType;
	}

	/**
	 * @param array|null $values
	 * @param ConversionContext $context
	 * @return string|null
	 */
	public function convertDatabaseValueToSemicolonDelimitedString(?array $values, ConversionContext $context): ?string
	{
		if (is_null($values)) {
			return null;
		}

		$output = [];
		foreach ($values as $value) {
			if ($this->itemDataType instanceof ArrayDataType) {
				throw new RuntimeException("Arrays of arrays are not supported");
			}
			$apiValue = $this->itemDataType->convertDatabaseValueToApiValue($value, $context);
			if (is_string($apiValue)) {
				// escape any semicolons in the value with a "\;"
				$apiValue = str_replace(";", "\\;", $apiValue);
			}
			$output[] = $apiValue;
		}
		return join(";", $output);
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return mixed|void
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		// assume that parameters will be CSV formatted

		// csvIterator assumes a file with multiple lines whereas we only want a single line
		$iterator = new csvIterator($userValue);
		$values = [];
		foreach ($iterator as $row) {
			foreach ($row as $column) {
				$values[] = $this->itemDataType->convertParameterValueToServerValue($column, $context);
			}
			break;
		}
		return $values;
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateParameterValue(string $userValue, ConversionContext $context): array
	{
		// assume that parameters will be CSV formatted

		// csvIterator assumes a file with multiple lines whereas we only want a single line
		$iterator = new csvIterator($userValue);
		$rowIndex = 0;
		foreach ($iterator as $row) {
			if ($rowIndex > 0) {
				return [false, "Unexpected new line in array"];
			}

			$columnIndex = 0;
			foreach ($row as $column) {
				list($isValid, $validationMessage) = $this->itemDataType->validateParameterValue($column, $context);
				if (!$isValid) {
					return [false, "Unexpected value at index $columnIndex. " . $validationMessage];
				}
				$columnIndex++;
			}
			$rowIndex++;
		}
		return [true, null];
	}

	/**
	 * Verifies that the value from the user is of the proper type or can be converted to the type on the server.
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array Return a pair, where the first index is true if the value is valid, otherwise false. The second
	 * index is a string that is the validation error that occurred.
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		if (!is_array($userValue) || (count($userValue) > 0 && !arrayTools::isIndexedArray($userValue))) {
			return [false, 'The value must be an array of ' . $this->itemDataType->getName() . '.'];
		}

		$index = 0;
		foreach ($userValue as $item) {
			if (is_null($item)) {
				return [false, "The value in the array at index $index is not valid. Items in array cannot be specified as null."];
			}
			list($itemValid, $itemError) = $this->itemDataType->validateJsonValue($item, $context);
			if (!$itemValid) {
				if (is_scalar($item)) {
					return [false, "The value \"$item\" in the array at index $index is not valid. " . $itemError];
				}
				return [false, "The value in the array at index $index is not valid. " . $itemError];
			}
			$index++;
		}

		// verify the minimum number of items
		if (count($userValue) < $this->minItems) {
			return [false, "The array must contain {$this->minItems} or more values."];
		}

		// every item passed the item validation so the array is good
		return [true, null];
	}

	/**
	 * Converts the value from the user to the server value.
	 *
	 * For some data types, the value from the user is not in the correct format and needs to be converted. For example,
	 * a timestamp is usually a string in the user's timezone however we want to convert to a DateTime in the server's
	 * timezone.
	 *
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		$convertedResults = [];
		foreach ($userValue as $item) {
			$convertedResults[] = $this->itemDataType->convertJsonValueToServerValue($item, $context);
		}
		return $convertedResults;
	}

	/**
	 * Converts the value from the database to the value in the API.
	 *
	 * For some data types, the value sent to the user in the response needs to be converted to a different format. For
	 * example, a timestamp in the database is usually represented as a formatted string in the server's timezone however
	 * the API value will be a string formatted in the user's timezone.
	 *
	 * @param mixed|null $dbValue The value from the database.
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The value found in the API.
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		throw new RuntimeException('Array is unsupported as an output type');
	}

	/**
	 * Converts the value from the database to the server value.
	 *
	 * For some data types, the value in the DB is not the correct format and/or type to use in the server code so
	 * it needs to be converted. For example, a timestamp is usually a string value in the server's timezone however
	 * the server code needs a DateTime.
	 *
	 * @param mixed $dbValue The value from the database
	 * @return mixed
	 */
	public function convertDatabaseValueToServerValue($dbValue)
	{
		if (!$dbValue) {
			return null;
		}
		if (is_string($dbValue)) {
			$dbValue = json_decode($dbValue);
		}
		$convertedResults = [];
		foreach ($dbValue as $item) {
			$convertedResults[] = $this->itemDataType->convertDatabaseValueToServerValue($item);
		}
		return $convertedResults;
	}

	public function isServerValueType($value): bool
	{
		if (!is_array($value)) {
			return false;
		}

		foreach ($value as $item) {
			if (!$this->itemDataType->isServerValueType($item)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Converts the value on the server to the value in the API.
	 *
	 * For some data types, the value sent to the user in the response needs to be converted to a different format. For
	 * example, a timestamp in the API is usually represented as a formatted string in the user's timezone however
	 * the server will be a DateTime (in it's own timezone).
	 *
	 * @param mixed $serverValue The value from the server
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The value to be sent to the user.
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
		if (!$serverValue) {
			return null;
		}
		$convertedResults = [];
		foreach ($serverValue as $item) {
			$convertedResults[] = $this->itemDataType->convertServerValueToApiValue($item, $context);
		}
		return $convertedResults;
	}

	/**
	 * @param TypedXMLOrJSONWriter $writer
	 * @param ConversionContext $context
	 * @param string $propertyName
	 * @param mixed $serverValue
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		if (is_null($serverValue)) {
			$writer->writeNullElement($propertyName);
			return;
		}
		if (!is_array($serverValue)) {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			throw new SerializationException("Expected value to be an array: {$msgValue}.");
		}

		// arrays are weird in the API since the same element is written multiple times (even if the format is JSON!)
		$itemDataType = $this->itemDataType;
		foreach ($serverValue as $arrayItem) {
			$itemDataType->writeServerValueToXmlWriter($writer, $context, $propertyName, $arrayItem);
		}
	}
}

<?php
namespace Api\DataTypes;

use \RuntimeException;
use TypedXMLOrJSONWriter;

/**
 * Data type to read a map object from user input
 */
class MapDataType implements DataType
{
	const NAME = "map";

	private DataType $itemDataType;
	private int $minItems;

	public function __construct(DataType $itemDataType, int $minItems)
	{
		$this->itemDataType = $itemDataType;
		$this->minItems = $minItems;
	}

	/**
	 * @return DataType
	 */
	public function getItemDataType(): DataType
	{
		return $this->itemDataType;
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		if (!is_array($userValue) ) {
			return [false, 'The value must be a map of ' . $this->itemDataType->getName() . '.'];
		}

		foreach($userValue as $key => $item) {
			if (empty($key)) {
				return [false, 'A null key in the map is not allowed'];
			}
			list($itemValid, $itemError) = $this->itemDataType->validateJsonValue($item, $context);
			if (!$itemValid) {
				return [false, "The value in map at [$key] is not valid. " . $itemError];
			}
		}

		// verify the minimum number of items
		if (count($userValue) < $this->minItems) {
			return [false, "The array must contain {$this->minItems} or more values."];
		}

		// every item passed the item validation so the array is good
		return [true, null];
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
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateParameterValue(string $userValue, ConversionContext $context): array
	{
		throw new RuntimeException('Method not implemented');
	}

	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		throw new RuntimeException('Method not implemented');
	}

	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		$convertedResults = [];
		foreach ($userValue as $key => $item) {
			$convertedResults[$key] = $this->itemDataType->convertJsonValueToServerValue($item, $context);
		}
		return $convertedResults;
	}

	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		throw new RuntimeException('Method not implemented');
	}

	public function convertDatabaseValueToServerValue($dbValue)
	{
		throw new RuntimeException('Method not implemented');
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

	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
		return $serverValue;
	}

	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		throw new RuntimeException('Method not implemented');
	}
}

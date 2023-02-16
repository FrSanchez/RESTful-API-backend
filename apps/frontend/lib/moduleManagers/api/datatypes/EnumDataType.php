<?php
namespace Api\DataTypes;

use Api\Exceptions\ApiException;
use Api\Objects\Enums\EnumField;
use Api\Serialization\SerializationException;
use RuntimeException;
use TypedXMLOrJSONWriter;
use ApiErrorLibrary;

class EnumDataType implements DataType
{
	const NAME = "enum";
	const STRICT_ERROR = 'The provided value did not match any of the Strings allowed. ';

	/** @var string $enumFieldClass */
	private $enumFieldClass;
	private $values;

	/**
	 * Enum constructor.
	 * @param string $enumFieldClass Provider for the enum values
	 */
	public function __construct(string $enumFieldClass)
	{
		$this->enumFieldClass = $enumFieldClass;
	}

	/**
	 * @return mixed
	 */
	public function getEnumValues()
	{
		$this->ensureEnumValuesLoaded();
		return $this->values;
	}

	/**
	 * @param array $values
	 * @return bool
	 */
	private function isValidateEnumArray(array $values): bool
	{
		foreach ($values as $key => $value) {
			if (is_null($value) || !is_string($value)) {
				return false;
			}

			// null keys in php are converted to empty strings
			if (!(is_null($key) || empty($key)) && !is_int($key)) {
				return false;
			}
		}

		return count(array_unique(array_values($values))) === count($values);
	}

	/**
	 * @param array $values
	 */
	private function convertValues(array &$values): void
	{
		foreach ($values as $key => $value) {
			$values[$key] = strtolower($value);
		}
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
		return $this->validateJsonValue($userValue, $context);
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return int
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context): int
	{
		return $this->convertJsonValueToServerValue($userValue, $context);
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		$this->ensureEnumValuesLoaded();
		if (is_string($userValue) && in_array(strtolower($userValue), $this->values)) {
			return [true, null];
		}

		return [false, self::STRICT_ERROR . implode(", ", array_values($this->values))];
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return int
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context): int
	{
		$this->ensureEnumValuesLoaded();
		if (!is_string($userValue) || !in_array(strtolower($userValue), $this->values)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN);
		}

		return array_search(strtolower($userValue), $this->values);
	}

	/**
	 * @param mixed|null $dbValue
	 * @param ConversionContext $context
	 * @return string
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context): string
	{
		$serverValue = $this->convertDatabaseValueToServerValue($dbValue);
		return $this->convertServerValueToApiValue($serverValue, $context);
	}

	/**
	 * @param mixed $dbValue
	 * @return int|null
	 */
	public function convertDatabaseValueToServerValue($dbValue): ?int
	{
		if (is_null($dbValue)) {
			return null;
		} elseif (is_string($dbValue) && ($integerValue = filter_var($dbValue, FILTER_VALIDATE_INT)) !== false) {
			return $integerValue;
		} elseif (is_int($dbValue)) {
			return (int)$dbValue;
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			throw new DataTypeConversionException("Unable to convert value to a integer: $msgValue. Expecting the value to be an integer for enums.");
		}
	}

	/**
	 * @param mixed $value
	 * @return bool
	 */
	public function isServerValueType($value): bool
	{
		return is_int($value);
	}

	/**
	 * @param mixed $serverValue
	 * @param ConversionContext $context
	 * @return string
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context): string
	{
		$this->ensureEnumValuesLoaded();
		$convertedValue = $this->convertDatabaseValueToServerValue($serverValue);
		return $this->values[$convertedValue];
	}

	/**
	 * @param TypedXMLOrJSONWriter $writer
	 * @param ConversionContext $context
	 * @param string $propertyName
	 * @param mixed $serverValue
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		try {
			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeStringElement($propertyName, $apiValue);
		} catch (DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(), null, $exc);
		}
	}

	private function ensureEnumValuesLoaded(): void
	{
		if ($this->values) {
			return;
		}

		$enumFieldClass = $this->enumFieldClass;
		if (!class_exists($enumFieldClass)) {
			throw new RuntimeException("Enum Field class {$enumFieldClass} not found.");
		}

		$enumFieldClassInstance = new $enumFieldClass();
		if (!($enumFieldClassInstance instanceof EnumField)) {
			throw new RuntimeException("The enum class {$enumFieldClass} is not an instance of EnumField.");
		}

		$values = $enumFieldClassInstance->getArray();
		$this->convertValues($values);
		if (empty($values) || !$this->isValidateEnumArray($values)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNKNOWN,
				"Non-empty associative array of integer and string expected for the enums or a validate enums array was not provided."
			);
		}
		$this->values = $values;
	}
}

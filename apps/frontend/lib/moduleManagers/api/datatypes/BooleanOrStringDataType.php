<?php
namespace Api\DataTypes;

use Api\Serialization\SerializationException;
use \Singleton;
use TypedXMLOrJSONWriter;

/**
 * Stopgap until support for multiple data types is supported in the framework (W-7170600).
 *
 * @package Api\DataTypes
 * @deprecated This should no longer be used.
 */
class BooleanOrStringDataType implements DataType
{
	const NAME = 'boolean-or-string';
	const ERROR = 'Expected value to be one of the following types: boolean, string.';

	use Singleton;

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
		list($isValidBoolean, $booleanValidationError) = BooleanDataType::getInstance()
			->validateParameterValue($userValue, $context);
		if ($isValidBoolean) {
			return [true, null];
		}
		list($isValidString, $stringValidationError) = StringDataType::getInstance()
			->validateJsonValue($userValue, $context);
		if ($isValidString) {
			return [true, null];
		}
		return [false, self::ERROR];
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return string|bool
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		list($isValidBoolean, $booleanValidationError) = BooleanDataType::getInstance()
			->validateParameterValue($userValue, $context);
		if ($isValidBoolean) {
			return BooleanDataType::getInstance()->convertParameterValueToServerValue($userValue, $context);
		}
		return StringDataType::getInstance()->convertParameterValueToServerValue($userValue, $context);
	}

	/**
	 * Verifies that a value provided by the user in JSON (from json_decode) is of the proper type.
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array Return a pair, where the first index is true if the value is valid, otherwise false. The second
	 * index is a string that is the validation error that occurred.
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		list($isValidBoolean, $booleanValidationError) = BooleanDataType::getInstance()
			->validateJsonValue($userValue, $context);
		if ($isValidBoolean) {
			return [true, null];
		}

		list($isValidString, $stringValidationError) = StringDataType::getInstance()
			->validateJsonValue($userValue, $context);
		if ($isValidString) {
			return [true, null];
		}

		return [false, self::ERROR];
	}

	/**
	 * Converts the value provided by the user in JSON (from json_decode) to the server value.
	 *
	 * For some data types, the value in JSON is not in the correct format and needs to be converted. For example,
	 * a timestamp is usually a string in the user's timezone however we want to convert to a DateTime in the server's
	 * timezone.
	 *
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		list($isValidBoolean, $booleanValidationError) = BooleanDataType::getInstance()
			->validateJsonValue($userValue, $context);
		if ($isValidBoolean) {
			return BooleanDataType::getInstance()->convertJsonValueToServerValue($userValue, $context);
		}
		return StringDataType::getInstance()->convertJsonValueToServerValue($userValue, $context);
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
		// API supports PHP boolean and strings so no special conversions needed
		return $this->convertDatabaseValueToServerValue($dbValue);
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
		if (is_null($dbValue)) {
			return null;
		} else if (is_int($dbValue) && ($dbValue === 0 || $dbValue === 1)) {
			// the DB will store booleans as a tiny integer so we need to convert it to a PHP boolean
			return $dbValue === 1;
		} else if (is_bool($dbValue)) {
			return $dbValue;
		} else if (is_string($dbValue)) {
			return (string) $dbValue;
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			$name = self::NAME;
			throw new DataTypeConversionException("Unable to convert value to a $name: $msgValue. Expecting the value to be a boolean or string.");
		}
	}

	public function isServerValueType($value): bool
	{
		return is_bool($value) || is_string($value);
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
		// API supports PHP boolean and strings so no special conversions needed
		return $this->convertDatabaseValueToServerValue($serverValue);
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
		} else if (is_bool($serverValue)) {
			BooleanDataType::getInstance()->writeServerValueToXmlWriter($writer, $context, $propertyName, $serverValue);
		} else if (is_string($serverValue)) {
			StringDataType::getInstance()->writeServerValueToXmlWriter($writer, $context, $propertyName, $serverValue);
		} else {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			$name = self::NAME;
			throw new SerializationException("Unable to convert value to a $name: $msgValue. Expecting the value to be a boolean or string.");
		}
	}
}

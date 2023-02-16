<?php
namespace Api\DataTypes;

use Api\Serialization\SerializationException;
use Singleton;
use TypedXMLOrJSONWriter;

class BooleanDataType implements DataType
{
	const NAME = 'boolean';
	const ERROR = 'Invalid boolean value. Please specify true or false.';

	use Singleton;

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
		if (strtolower($userValue) === 'true' || strtolower($userValue) === 'false') {
			return [true, null];
		}
		return [false, self::ERROR];
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return mixed|void
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		if (is_null($userValue)) {
			return null;
		}
		if (strtolower($userValue) === 'true') {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		// not using convertToBoolean since we want strict boolean validation (true/false)
		if ($userValue === true || $userValue === false) {
			return [true, null];
		}
		return [false, self::ERROR];
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return bool|null
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		// JSON supports booleans so no conversion
		return (bool)$userValue;
	}

	/**
	 * Converts the value on the database to a boolean value for use in the API.
	 *
	 * @param mixed|null $dbValue The value from the database.
	 * @param ConversionContext $context The context of the conversion.
	 * @return bool|null The value to be sent to the user.
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		// API supports PHP boolean so we only need to convert from DB to server value
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
	 * @return bool|null
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
		} else if (is_string($dbValue) && ($dbValue === "0" || $dbValue === "1")) {
			return $dbValue === "1";
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			throw new DataTypeConversionException("Unable to convert value to a boolean: $msgValue. Expecting either a boolean or integer.");
		}
	}

	public function isServerValueType($value): bool
	{
		return is_bool($value);
	}

	/**
	 * Converts the value on the server to a boolean value for use in the API.
	 *
	 * @param mixed $serverValue The value from the server
	 * @param ConversionContext $context The context of the conversion.
	 * @return bool The value to be sent to the user.
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
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
		if (is_null($serverValue) || $serverValue === true || $serverValue === false) {
			$writer->writeBooleanElement($propertyName, $serverValue);
		} else {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			throw new SerializationException("Expected value to be a boolean: {$msgValue}.");
		}
	}
}

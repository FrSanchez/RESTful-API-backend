<?php
namespace Api\DataTypes;

use Api\Serialization\SerializationException;
use Singleton;
use TypedXMLOrJSONWriter;

/**
 * API data type that converts to a PHP int.
 *
 * Class IntegerDataType
 * @package Api\DataTypes
 */
class IntegerDataType implements DataType
{
	const NAME = 'integer';
	const ERROR = 'Invalid integer value.';
	const STRICT_ERROR = 'Expected value to be an integer.';

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
		if (filter_var($userValue, FILTER_VALIDATE_INT) !== false) {
			return [true, null];
		}
		return [false, self::STRICT_ERROR];
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return int|mixed
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		return (int)$userValue;
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		if (is_int($userValue)) {
			return [true, null];
		}
		return [false, self::STRICT_ERROR];
	}

	/**
	 * For some data types, the value from the user is not in the correct format and needs to be converted. For example,
	 * a timestamp is usually a string in the user's timezone however we want to convert to a DateTime in the server's
	 * timezone.
	 *
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context): ?int
	{
		// JSON supports integers so it should already be a PHP int
		return (int)$userValue;
	}

	/**
	 * Converts the value from the database to the value in the API.
	 * @param mixed|null $dbValue The integer from the database.
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The integer value used in the API.
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context): ?int
	{
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
	 * @return int|null
	 */
	public function convertDatabaseValueToServerValue($dbValue): ?int
	{
		if (is_null($dbValue)) {
			return null;
		} else if (is_string($dbValue) && ($integerValue = filter_var($dbValue, FILTER_VALIDATE_INT)) !== false) {
			// The DB may return integers as PHP strings so convert to PHP integers
			return $integerValue;
		} else if (is_int($dbValue)) {
			// The DB should be returning PHP integers and the API understands PHP integers so no conversion needs to be done.
			return (int)$dbValue;
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			throw new DataTypeConversionException("Unable to convert value to a integer: $msgValue. Expecting the value to be an integer.");
		}
	}

	public function isServerValueType($value): bool
	{
		return is_int($value);
	}

	/**
	 * Converts the value on the server to the value in the API.
	 * @param mixed|null $serverValue The integer from the server.
	 * @param ConversionContext $context The context of the conversion.
	 * @return int|null The integer value used in the API.
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context): ?int
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
		try {
			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeIntegerElement($propertyName, $apiValue);
		} catch(DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(),null, $exc);
		}
	}
}

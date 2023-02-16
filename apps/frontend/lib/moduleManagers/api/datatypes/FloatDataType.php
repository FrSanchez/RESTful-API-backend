<?php
namespace Api\DataTypes;

use Api\Exceptions\ApiException;
use Api\Serialization\SerializationException;
use ApiErrorLibrary;
use Singleton;
use TypedXMLOrJSONWriter;

/**
 * API data type that converts to a PHP float.
 *
 * Class FloatDataType
 * @package Api\DataTypes
 */
class FloatDataType implements DataType
{
	const NAME = 'float';
	const ERROR = 'Invalid float value.';
	const STRICT_ERROR = 'Expected value to be a float.';

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
		if (filter_var($userValue, FILTER_VALIDATE_FLOAT) !== false) {
			return [true, null];
		}
		return [false, self::STRICT_ERROR];
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return float|mixed
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		return (float)$userValue;
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		if (is_float($userValue) || is_int($userValue)) {
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
	public function convertJsonValueToServerValue($userValue, ConversionContext $context): ?float
	{
		if (!is_float($userValue) && !is_int($userValue)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN);
		}
		// JSON supports floats so it should already be a PHP float
		return (float)$userValue;
	}

	/**
	 * Converts the value from the database to the value in the API.
	 * @param mixed|null $dbValue The number from the database.
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The number value used in the API.
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context): ?float
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
	 * @return float|null
	 */
	public function convertDatabaseValueToServerValue($dbValue): ?float
	{
		if (is_null($dbValue)) {
			return null;
		} else if (is_string($dbValue) && ($floatValue = filter_var($dbValue, FILTER_VALIDATE_FLOAT)) !== false) {
			// The DB may return floats as PHP strings so convert to PHP floats
			return $floatValue;
		} else if (is_float($dbValue) || is_int($dbValue)) {
			// The DB should be returning PHP floats and the API understands PHP floats so no conversion needs to be done.
			return (float)$dbValue;
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			throw new DataTypeConversionException("Unable to convert value to a float: $msgValue. Expecting the value to be a float.");
		}
	}

	public function isServerValueType($value): bool
	{
		return is_float($value);
	}

	/**
	 * Converts the value on the server to the value in the API.
	 * @param mixed|null $serverValue The float from the server.
	 * @param ConversionContext $context The context of the conversion.
	 * @return float|null The float value used in the API.
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context): ?float
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
		if (is_null($serverValue)) {
			$writer->writeNullElement($propertyName);
			return;
		}

		try {
			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeFloatElement($propertyName, $apiValue);
		} catch(DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(),null, $exc);
		}
	}
}

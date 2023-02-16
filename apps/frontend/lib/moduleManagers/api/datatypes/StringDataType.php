<?php
namespace Api\DataTypes;

use Api\Exceptions\ApiException;
use Api\Serialization\SerializationException;
use ApiErrorLibrary;
use Singleton;
use TypedXMLOrJSONWriter;

/**
 * API data type that converts to a PHP string
 *
 * Class StringDataType
 * @package Api\DataTypes
 */
class StringDataType implements DataType
{
	const NAME = 'string';
	const ERROR = 'Unable to convert value to a string';
	const STRICT_ERROR = 'Expected value to be a string.';

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
		// a string value is always valid
		return [true, null];
	}

	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		return $userValue;
	}

	/**
	 * @param $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		if (is_string($userValue)) {
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
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		// PHP has automatic conversion for scalar types so handle that as a special case
		if (!is_string($userValue)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN);
		}
		return (string) $userValue;
	}

	/**
	 * Converts the value from the database to the value in the API.
	 * @param mixed|null $dbValue The string from the database.
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The string value used in the API.
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
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
	 * @param mixed|null $dbValue The value from the database
	 * @return string|null
	 */
	public function convertDatabaseValueToServerValue($dbValue)
	{
		if (is_null($dbValue)) {
			return null;
		}
		if (is_numeric($dbValue) || is_string($dbValue)) {
			return strval($dbValue);
		}
		$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
		throw new DataTypeConversionException("Unable to convert value to a string: $msgValue. Expecting the value to be a string.");
	}

	public function isServerValueType($value): bool
	{
		return is_string($value);
	}

	/**
	 * Converts the value on the server to the value in the API.
	 * @param mixed|null $value The string from the server.
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed|null The string value used in the API.
	 */
	public function convertServerValueToApiValue($value, ConversionContext $context)
	{
		return $this->convertDatabaseValueToServerValue($value);
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
			if (is_null($serverValue)) {
				$writer->writeNullElement($propertyName);
				return;
			}

			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeStringElement($propertyName, $apiValue);
		} catch(DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(),null, $exc);
		}
	}
}

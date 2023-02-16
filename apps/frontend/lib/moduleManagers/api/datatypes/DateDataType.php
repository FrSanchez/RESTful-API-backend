<?php


namespace Api\DataTypes;
use Api\Exceptions\ApiException;
use Api\Serialization\SerializationException;
use ApiErrorLibrary;
use DateTime;
use TypedXMLOrJSONWriter;
use Singleton;


/**
 * DateDataType mostly follows the same rules as StringDatatype with validation for date format 'Y-m-d'
 * Class DateDataType
 * @package Api\DataTypes
 */
class DateDataType implements DataType
{
	const NAME = 'date';
	const ERROR = 'Invalid date value';
	const STRICT_ERROR = 'Expected the date value to be a string.';

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
		// Json and Parameter values use strings for dates
		return $this->validateJsonValue($userValue, $context);
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return mixed|void
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		// both JSON and Parameter use strings for dates
		return $this->convertJsonValueToServerValue($userValue, $context);
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		// date must be a string
		if (!is_string($userValue) || is_null($userValue) || strlen(trim($userValue)) == 0) {
			return [false, self::STRICT_ERROR];
		}
		$valid = \dateTools::isValidDateFieldValue($userValue);

		return [
			$valid,
			$valid ? null : self::ERROR
		];
	}


	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return string with date format 'Y-m-d' | null
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		if (!is_string($userValue) || strlen(trim($userValue)) == 0 || !\dateTools::isValidDateFieldValue($userValue)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN);
		}
		return (string)$userValue;

	}

	/**
	 * Converts the value from the database to the value in the API.
	 *
	 * @param string|null $dbValue The value from the server
	 * @param ConversionContext $context The context of the conversion.
	 * @return string|null The value to be sent to the user.
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
	 * @param string|null $dbValue The value from the database
	 * @return string|null
	 */
	public function convertDatabaseValueToServerValue($dbValue)
	{
		if (is_null($dbValue)) {
			return null;
		} else if (!is_string($dbValue)) {
			throw new DataTypeConversionException("Unable to create DateDataType from value. invalid datatype. ");
		} else if (strlen(trim($dbValue)) === 0) {
			return null;
		}

		$isValidDateValue = \dateTools::isValidDateFieldValue($dbValue);
		if ($isValidDateValue) {
			return $dbValue;
		} else {
			throw new DataTypeConversionException("Unable to create DateDataType from value '$dbValue'.");
		}
	}

	public function isServerValueType($value): bool
	{
		return $value instanceof DateTime ||
			(is_string($value) && strlen(trim($value)) > 0 && \dateTools::isValidDateFieldValue($value));
	}

	/**
	 * Converts the value on the server to the value in the API.
	 *
	 * @param string|DateTime|null $serverValue The value from the server
	 * @param ConversionContext $context The context of the conversion.
	 * @return string|null The value to be sent to the user.
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
		// we expect the server value to always be either null or string
		if (!is_null($serverValue) && !(is_string($serverValue))) {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			throw new SerializationException("Expected value to be either null or a string: $msgValue.");
		}

		if (is_null($serverValue)) {
			$writer->writeNullElement($propertyName);
			return;
		}

		try {
			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeStringElement($propertyName, $apiValue);
		} catch (DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(), null, $exc);
		}
	}
}

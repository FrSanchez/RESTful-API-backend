<?php
namespace Api\DataTypes;

use Api\Serialization\SerializationException;
use DateTime;
use DateTimeZone;
use generalTools;
use Singleton;
use TimezoneManager;
use TypedXMLOrJSONWriter;
use DateTimeInterface;

class DateTimeDataType implements DataType
{
	const NAME = 'datetime';
	const ERROR = 'Invalid date time value';
	const STRICT_ERROR = 'Expected the date time value to be a string.';

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
		// both JSON and Parameter use strings for dates
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
		// date time must be a string
		if (!is_string($userValue) || is_null($userValue)) {
			return [false, self::STRICT_ERROR];
		}

		$valid = generalTools::stringToDateTime($userValue, new DateTimeZone(date_default_timezone_get())) ? true : false;

		// Check if the version is 5 or higher and the $userValue is an actual datetime
		if ($context->getVersion() >= 5) {
			$valid = $this->checkDateFormat($userValue, DateTimeInterface::ATOM);
		}

		return [
			$valid,
			$valid ? null : self::ERROR
		];
	}

	/**
	 * @param mixed $userValue
	 * @param string $format
	 * @return bool
	 */
	private function checkDateFormat($userValue, string $format): bool
	{
		$d = DateTime::createFromFormat($format, $userValue);
		return $d && $d->format($format) === $userValue;
	}

	/**
	 * @param mixed $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return DateTime|null
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		// JSON dates are strings but the server needs them in PHP DateTime so convert
		$tzCaller = $context->getTimezone();

		// Datetime timezone should be from the $userValue itself in v5 or higher
		if ($context->getVersion() >= 5) {
			$date = DateTime::createFromFormat(DateTimeInterface::ATOM, $userValue);
			$tzCaller = $date->getTimezone();
		}

		$tzServer = new DateTimeZone(date_default_timezone_get());
		return generalTools::stringToDateTime($userValue, $tzCaller, $tzServer);
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
		$serverValue = $this->convertDatabaseValueToServerValue($dbValue);
		return $this->convertServerValueToApiValue($serverValue, $context);
	}

	/**
	 * Converts the value from the database to the server value.
	 *
	 * For some data types, the value in the DB is not the correct format and/or type to use in the server code so
	 * it needs to be converted. For example, a timestamp is usually a string value in the server's timezone however
	 * the server code needs a DateTime.
	 *
	 * @param string|null $dbValue The value from the database
	 * @return DateTime|null
	 */
	public function convertDatabaseValueToServerValue($dbValue)
	{
		if (is_null($dbValue)) {
			return null;
		} elseif (is_string($dbValue)) {
			if (empty($dbValue)) {
				return null;
			}

			// the DB will store datetime values as a string so we need to convert them to DateTime
			$timestamp = date_create($dbValue);
			if (!$timestamp) {
				throw new DataTypeConversionException("Unable to create DateTime from value '$dbValue'.");
			}
			return $timestamp;
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			$name = self::NAME;
			throw new DataTypeConversionException(
				"Unable to convert value to a $name: $msgValue. Expecting the value to be a string."
			);
		}
	}

	public function isServerValueType($value): bool
	{
		return $value instanceof DateTime;
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
		$format = null;
		if ($context->getVersion() >= 5) {
			$format = DateTimeInterface::ATOM;
		}

		if (is_null($serverValue)) {
			return null;
		} elseif (is_object($serverValue) && $serverValue instanceof DateTime) {
			// converting from DateTime to string to DateTime (within TimezoneManager::getAdjustedTime) is really
			// inefficient. This needs to be refactored so that DateTime can be passed as arg to TimezoneManager.
			$timezoneId = $context->getTimezone()->getName();
			$valueAsString = \dateTools::mysqlFormat($serverValue);
			return TimezoneManager::getAdjustedTime($timezoneId, $valueAsString, true, $format);
		} elseif (is_string($serverValue)) {
			// the DB will store datetime values as a string so we need to adjust it for timezone
			$timezoneId = $context->getTimezone()->getName();
			return TimezoneManager::getAdjustedTime($timezoneId, $serverValue, true, $format);
		} else {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			$name = self::NAME;
			throw new DataTypeConversionException(
				"Unable to convert value to a $name: $msgValue. Expecting the value to be a string."
			);
		}
	}

	/**
	 * @param TypedXMLOrJSONWriter $writer
	 * @param ConversionContext $context
	 * @param string $propertyName
	 * @param mixed $serverValue
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		// we expect the server value to always be either null or DateTime
		if (!is_null($serverValue) && !($serverValue instanceof DateTime)) {
			$msgValue = is_scalar($serverValue) ? strval($serverValue) : gettype($serverValue);
			throw new SerializationException("Expected value to be of type DateTime: $msgValue.");
		}

		try {
			$apiValue = $this->convertServerValueToApiValue($serverValue, $context);
			$writer->writeStringElement($propertyName, $apiValue);
		} catch (DataTypeConversionException $exc) {
			throw new SerializationException($exc->getConversionDetails(), null, $exc);
		}
	}
}

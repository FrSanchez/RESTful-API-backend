<?php

namespace Api\DataTypes;

use Api\Exceptions\ApiException;
use ApiErrorLibrary;
use DateTime;
use Exception;
use RuntimeException;
use Singleton;
use TypedXMLOrJSONWriter;

/**
 * TimeDataType mostly follows the same rules as DateDatatype with validation for time format 'H:i:s'
 */
class TimeDataType implements DataType
{
	use Singleton;
	public const NAME = 'time';
	public const ERROR = 'Invalid time value';
	public const STRICT_ERROR = 'Expected the time value to be a string.';
	public const FORMAT = 'H:i:s';
	private const WORKFLOW_BUSINESS_HOURS_DB_TIME_FORMAT = 'H:i:s.v';

	/**
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
		// both JSON and Parameter use strings for dates
		return $this->validateJsonValue($userValue, $context);
	}

	/**
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return string
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context): string
	{
		// both JSON and Parameter use strings for dates
		return $this->convertJsonValueToServerValue($userValue, $context);
	}

	/**
	 * @param $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		// time must be a string
		if (empty($userValue) || !is_string($userValue)) {
			return [false, self::STRICT_ERROR];
		}

		// Check if the $userValue is provided in the H:i:s format
		$valid = $this->checkTimeFormat($userValue, self::FORMAT);

		return [
			$valid,
			$valid ? null : self::ERROR
		];
	}

	/**
	 * @param $userValue
	 * @param ConversionContext $context
	 * @return string|null string with time format 'H:i:s'
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context): ?string
	{
		if (empty($userValue)  || !is_string($userValue)) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN);
		} elseif (!$this->checkTimeFormat($userValue, self::FORMAT)) {
			throw new DataTypeConversionException("Unable to create time from value '$userValue'.");
		}
		return (string)$userValue;
	}

	/**
	 * @param $dbValue
	 * @param ConversionContext $context
	 * @return string|null
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context): ?string
	{
		return $this->convertDatabaseValueToServerValue($dbValue);
	}

	/**
	 * @param $dbValue
	 * @return string|null
	 */
	public function convertDatabaseValueToServerValue($dbValue): ?string
	{
		if (is_null($dbValue) || (empty($dbValue) && is_string($dbValue))) {
			return null;
		}

		if (is_string($dbValue) && $this->checkTimeFormat($dbValue, self::WORKFLOW_BUSINESS_HOURS_DB_TIME_FORMAT)) {
			$datetime = DateTime::createFromFormat(self::WORKFLOW_BUSINESS_HOURS_DB_TIME_FORMAT, $dbValue);
			return $datetime->format(self::FORMAT);
		} else {
			$msgValue = is_scalar($dbValue) ? strval($dbValue) : gettype($dbValue);
			$name = self::NAME;
			throw new DataTypeConversionException(
				"Unable to convert value to a $name: $msgValue. Expecting the value to be a string."
			);
		}
	}

	/**
	 * @param $serverValue
	 * @param ConversionContext $context
	 * @return string|null
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context): ?string
	{
		return $this->convertDatabaseValueToServerValue($serverValue);
	}

	/**
	 * @param TypedXMLOrJSONWriter $writer
	 * @param ConversionContext $context
	 * @param string $propertyName
	 * @param $serverValue
	 * @return void
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		throw new RuntimeException('Method not implemented');
	}

	/**
	 * @param $value
	 * @return bool
	 */
	public function isServerValueType($value): bool
	{
		return is_string($value) && $this->checkTimeFormat($value, self::FORMAT);
	}

	/**
	 * Method used to validate if the input time is in the desired format
	 * @param string $time
	 * @param string $format
	 * @return bool
	 */
	private function checkTimeFormat(string $time, string $format): bool
	{
		try {
			return (new DateTime($time))->format($format) === $time;
		} catch (Exception $e) {
			return false;
		}
	}
}

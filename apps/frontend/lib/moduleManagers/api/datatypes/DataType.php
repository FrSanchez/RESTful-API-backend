<?php
namespace Api\DataTypes;

use TypedXMLOrJSONWriter;

/**
 * Represents a datatype within the API.
 *
 * A value within the API framework is represented in four different ways:
 *
 *     (1) user - The value from the user. For datetime, this is a string and could be a token like "now" or a date
 *         formatted in a couple of different formats.
 *     (2) server - The value from the user after it's been verified and converted. For datetime, this is a DateTime value.
 *     (3) database - This is the value as stored in the database. For datetime, this is a string formatted as a MySQL
 *         date in server timezone (usually EST).
 *     (4) api - This is the value returned to the user in the API. For datetime, this is a string formatted in one of
 *         the selected formats by the request (mysql, ISO8601) or as a number in unix/epoch and converted to either UTC
 *         or the user's timezone.
 *
 * Interface DataType
 * @package Api\DataTypes
 */
interface DataType
{
	/**
	 * Gets the name of the data type.
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Verifies that a value provided by the user from a parameter is of the property type. The term "Parameter" is used
	 * loosely - this could be a value from a query string parameter, form parameter, header value, etc.
	 *
	 * @param string $userValue
	 * @param ConversionContext $context
	 * @return array
	 */
	public function validateParameterValue(string $userValue, ConversionContext $context): array;

	/**
	 * Converts the value provided by the user from a parameter to the server value.
	 *
	 * For most data types, the string based value is not usable by the server and needs to be converted. For example,
	 * an integer passed into a query string parameter is a string in $_REQUEST, which must be converted to an integer
	 * in PHP.
	 *
	 * @param string $userValue
	 * @param ConversionContext $context The context of the conversion.
	 * @return mixed
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context);

	/**
	 * Verifies that a value provided by the user in JSON (from json_decode) is of the proper type.
	 * @param mixed $userValue
	 * @param ConversionContext $context
	 * @return array Return a pair, where the first index is true if the value is valid, otherwise false. The second
	 * index is a string that is the validation error that occurred.
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array;

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
	public function convertJsonValueToServerValue($userValue, ConversionContext $context);

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
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context);

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
	public function convertDatabaseValueToServerValue($dbValue);

	/**
	 * Determines if the given value is a valid server value type. Usually this method will only return true when
	 *   - the PHP type matches that of the type returned by {@see convertDatabaseValueToServerValue}
	 *   - the range of the value is within expected bounds
	 *   - the format of the value is correct
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public function isServerValueType($value): bool;

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
	public function convertServerValueToApiValue($serverValue, ConversionContext $context);

	/**
	 * Writes the value from the server to the given writer. This method will throw an exception if value is not of the
	 * expected server type.
	 *
	 * @param TypedXMLOrJSONWriter $writer
	 * @param ConversionContext $context
	 * @param string $propertyName
	 * @param mixed $serverValue
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void;
}

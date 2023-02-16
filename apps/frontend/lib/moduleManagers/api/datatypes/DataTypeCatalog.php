<?php
namespace Api\DataTypes;

/**
 * A catalog of all data types available in the API.
 *
 * Class DataTypeCatalog
 * @package Api\DataTypes
 */
class DataTypeCatalog
{
	private static array $PRIMITIVE_TYPE_NAMES = [
		BooleanDataType::NAME,
		BooleanOrStringDataType::NAME,
		DateTimeDataType::NAME,
		DateDataType::NAME,
		IntegerDataType::NAME,
		StringDataType::NAME,
		FloatDataType::NAME,
		TimeDataType::NAME
	];

	/**
	 * Gets the data type by the specified name or throws an exception when the name does not correspond to a known
	 * data type is not found.
	 * @param string $name The name of the data type. The name is case-sensitive!
	 * @return DataType
	 */
	public static function getDataTypeByName(string $name): DataType
	{
		switch ($name) {
			case BooleanDataType::NAME:
				return BooleanDataType::getInstance();

			case BooleanOrStringDataType::NAME:
				return BooleanOrStringDataType::getInstance();

			case DateTimeDataType::NAME:
				return DateTimeDataType::getInstance();

			case DateDataType::NAME:
				return DateDataType::getInstance();

			case IntegerDataType::NAME:
				return IntegerDataType::getInstance();

			case StringDataType::NAME:
				return StringDataType::getInstance();

			case FloatDataType::NAME:
				return FloatDataType::getInstance();

			case TimeDataType::NAME:
				return TimeDataType::getInstance();

			default:
				throw new \RuntimeException('Unknown data type specified: ' . $name . '. Must be one of ' . join(', ', self::$PRIMITIVE_TYPE_NAMES));
		}
	}

	/**
	 * Determines if the data type is one of the primitive data types.
	 * @param string $name
	 * @return bool
	 */
	public static function isPrimitiveDataTypeName(string $name): bool
	{
		return array_search($name, self::$PRIMITIVE_TYPE_NAMES) !== false;
	}

	/**
	 * Retrieves the appropriate data-type by the pi database's data-type enumeration
	 * @param int $enum
	 * @return DataType
	 */
	public static function getDataTypeByDatabaseEnum(int $enum): DataType
	{
		switch ($enum) {
			case \FormFieldPeer::TYPE_DATE:
				return DateDataType::getInstance();

			case \FormFieldPeer::TYPE_NUMBER:
				return FloatDataType::getInstance();

			/** @todo Change these types to array once the ArrayDataType is functional in W-7736624 */
			case \FormFieldPeer::TYPE_CHECKBOX:
			case \FormFieldPeer::TYPE_MULTI_SELECT:
				return new ArrayDataType(StringDataType::getInstance(), 1);


			/** Treat other field-types as strings */
			case \FormFieldPeer::TYPE_EMAIL:
			case \FormFieldPeer::TYPE_EMAIL_VALID_MX:
			case \FormFieldPeer::TYPE_EMAIL_NON_FREE:
			case \FormFieldPeer::TYPE_FK:
			case \FormFieldPeer::TYPE_CRM_USER:
			case \FormFieldPeer::TYPE_HIDDEN:
			case \FormFieldPeer::TYPE_TEXTAREA:
			case \FormFieldPeer::TYPE_DROPDOWN:
			case \FormFieldPeer::TYPE_RADIO_BUTTON:
			case \FormFieldPeer::TYPE_TEXT:
				return StringDataType::getInstance();

			default:
				throw new \RuntimeException('Unknown data type enumeration specified: ' . $enum . '.');
		}
	}
}

<?php
namespace Api\DataTypes;

abstract class ValueConverter
{
	/** @var ValueConverter */
	private static $dbToServerValueConverter;

	/**
	 * @param DataType $dataType
	 * @param mixed $value
	 * @return mixed
	 */
	abstract public function convert(DataType $dataType, $value);

	/**
	 * @return ValueConverter
	 */
	public static function createDbToServerValueConverter(): ValueConverter
	{
		if (!self::$dbToServerValueConverter) {
			self::$dbToServerValueConverter = new class() extends ValueConverter {
				/**
				 * @param DataType $dataType
				 * @param mixed $value
				 * @return mixed
				 */
				public function convert(DataType $dataType, $value)
				{
					return $dataType->convertDatabaseValueToServerValue($value);
				}
			};
		}

		return self::$dbToServerValueConverter;
	}

	/**
	 * @param ConversionContext $conversionContext
	 * @return ValueConverter
	 */
	public static function createDbToApiValueConverter(ConversionContext $conversionContext): ValueConverter
	{
		return new class($conversionContext) extends ValueConverter
		{
			/** @var ConversionContext $conversionContext */
			private $conversionContext;

			public function __construct(ConversionContext $conversionContext)
			{
				$this->conversionContext = $conversionContext;
			}

			/**
			 * @param DataType $dataType
			 * @param mixed $value
			 * @return mixed
			 */
			public function convert(DataType $dataType, $value)
			{
				return $dataType->convertDatabaseValueToApiValue($value, $this->conversionContext);
			}
		};
	}
}

<?php
namespace Api\DataTypes;

use Api\Exceptions\ApiException;
use Throwable;

/**
 * Exception thrown when a value cannot be converted to a DataType.
 *
 * Class DataTypeConversionException
 * @package Api\DataTypes
 */
class DataTypeConversionException extends ApiException
{
	/** @var string */
	private $conversionDetails;

	public function __construct(string $conversionDetails, Throwable $previous = null)
	{
		// Conversion details are *not* added as error details because error details are shown to the user
		// which may expose PII. Instead show a generic message to the user.
		parent::__construct(\ApiErrorLibrary::API_ERROR_UNKNOWN, null, 500, $previous);
		$this->conversionDetails = $conversionDetails;
	}

	/**
	 * @return string
	 */
	public function getConversionDetails(): string
	{
		return $this->conversionDetails;
	}
}

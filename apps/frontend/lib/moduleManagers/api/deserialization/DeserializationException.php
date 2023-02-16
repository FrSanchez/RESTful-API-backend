<?php
namespace Api\Deserialization;

use Api\Exceptions\ApiException;
use Throwable;

/**
 * Exception that is thrown when there is an error serializing a value.
 *
 * Class DeserializationException
 * @package Api\Deserialization
 */
class DeserializationException extends ApiException
{
	/** @var string */
	private $deserializationDetails;

	public function __construct(string $deserializationDetails, string $errorDetails = null, Throwable $previous = null)
	{
		// Deserialization details are *not* added as error details because error details are shown to the user
		// which may expose PII. Instead show a generic message to the user.
		parent::__construct(\ApiErrorLibrary::API_ERROR_UNKNOWN, $errorDetails, 500, $previous);
		$this->deserializationDetails = $deserializationDetails;
	}

	/**
	 * @return string
	 */
	public function getDeserializationDetails(): string
	{
		return $this->deserializationDetails;
	}
}

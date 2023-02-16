<?php
namespace Api\Serialization;

use Api\Exceptions\ApiException;
use Throwable;

/**
 * Exception that is thrown when there is an error serializing a value.
 *
 * Class SerializationException
 * @package Api\Serialization
 */
class SerializationException extends ApiException
{
	/** @var string */
	private $serializationDetails;

	public function __construct(string $serializationDetails, string $errorDetails = null, Throwable $previous = null)
	{
		// Serialization details are *not* added as error details because error details are shown to the user
		// which may expose PII. Instead show a generic message to the user.
		parent::__construct(\ApiErrorLibrary::API_ERROR_UNKNOWN, $errorDetails, 500, $previous);
		$this->serializationDetails = $serializationDetails;
	}

	/**
	 * @return string
	 */
	public function getSerializationDetails(): string
	{
		return $this->serializationDetails;
	}
}

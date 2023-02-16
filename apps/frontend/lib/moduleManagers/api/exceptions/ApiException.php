<?php
namespace Api\Exceptions;

use Throwable;

/**
 * An error that occurs in the API that sets the HTTP status code and response text.
 *
 * Class ApiException
 * @package Api\Exceptions
 */
class ApiException extends \RuntimeException
{
	/** @var int */
	private $errorCode;

	/** @var string */
	private $errorDetails;

	/** @var int */
	private $httpCode;

	/** @var array */
	private $headers;

	/**
	 * ApiException constructor.
	 * @param int $errorCode
	 * @param string|null $errorDetails
	 * @param int $httpCode
	 * @param Throwable|null $previous
	 * @param string[] $headers Associative array of HTTP headers to be added to the error response.
	 */
	public function __construct(int $errorCode = -1, $errorDetails = null, int $httpCode = 500, ?Throwable $previous = null, array $headers = [])
	{
		parent::__construct(self::createBaseExceptionMessage($errorCode, $errorDetails), $errorCode, $previous);
		$this->errorCode = $errorCode;
		$this->errorDetails = $errorDetails;
		$this->httpCode = $httpCode;
		$this->headers = $headers;
	}

	/**
	 * @return int
	 */
	public function getErrorCode(): int
	{
		return $this->errorCode;
	}

	/**
	 * @return string|null
	 */
	public function getErrorDetails()
	{
		return $this->errorDetails;
	}

	/**
	 * @return int
	 */
	public function getHttpCode(): int
	{
		return $this->httpCode;
	}

	/**
	 * @return array
	 */
	public function getHttpHeaders(): array
	{
		return $this->headers;
	}

	/**
	 * @param int $errorCode
	 * @param string|null $errorDetails
	 * @return string
	 */
	private static function createBaseExceptionMessage(int $errorCode, $errorDetails): string
	{
		if ($errorCode > -1) {
			$errorCodeText = \ApiErrorLibrary::getApiErrorMessage($errorCode);
		} else {
			$errorCodeText = 'Unknown Error';
		}

		if (!is_null($errorDetails) && strlen($errorDetails) > 0) {
			return $errorCodeText . ': ' . $errorDetails;
		}
		return $errorCodeText;
	}
}

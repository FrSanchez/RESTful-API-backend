<?php
namespace Api\Framework;

use Api\Representations\Representation;
use DateTime;
use DateTimeZone;
use RESTClient;
use RuntimeException;

class ResponseBuilder
{
	/** @var int */
	private $statusCode = RESTClient::HTTP_OK;

	/** @var Representation|null */
	private $representation;

	/** @var array */
	private $headers = [];

	/** @var int[] */
	private $warnings = [];

	public function withStatusCode(int $statusCode): self
	{
		$this->statusCode = $statusCode;
		return $this;
	}

	/**
	 * @param Representation $representation
	 * @return $this
	 */
	public function withRepresentation(Representation $representation): self
	{
		$this->representation = $representation;
		return $this;
	}

	/**
	 * Sets the header with the specified name to a value. If the header already exists, the
	 * header is replaced with this value.
	 * @param string $name The name of the header.
	 * @param string $value The new value of the header.
	 * @return $this
	 */
	public function withHeader(string $name, string $value): self
	{
		// don't allow direct modification of ApiWarning Header
		if (strtolower($name) === ApiWarnings::HTTP_HEADER) {
			throw new RuntimeException('Do not modify ' . ApiWarnings::HTTP_HEADER . ' directly. Use appendWarning instead.');
		}

		$this->headers[$name] = $value;
		return $this;
	}

	/**
	 * Sets the header with a DateTime value using RFC7231 format. If the header already exists,
	 * the header is replaced with this value.
	 * @param string $name The name of the header.
	 * @param DateTime $dateTime The new value of the header.
	 * @return $this
	 */
	public function withDateHeader(string $name, DateTime $dateTime): self
	{
		$utcDateTime = clone $dateTime;
		$utcDateTime->setTimezone(new DateTimeZone('UTC'));
		$this->withHeader($name, $utcDateTime->format(DateTime::RFC7231));
		return $this;
	}

	/**
	 * @param DateTime $dateTime
	 * @return $this
	 */
	public function withLastModifiedHeader(DateTime $dateTime): self
	{
		$this->withDateHeader('Last-Modified', $dateTime);
		return $this;
	}

	/**
	 * @param int $warningCode
	 * @return $this
	 */
	public function appendWarning(int $warningCode): self
	{
		$this->warnings[] = $warningCode;
		return $this;
	}

	/**
	 * @return Response
	 */
	public function build(): Response
	{
		$allHeaders = $this->headers;
		if (count($this->warnings) > 0) {
			$allHeaders[ApiWarnings::HTTP_HEADER] = ApiWarnings::createWarningMessageForHttpHeader(...$this->warnings);
		}

		return new Response(
			$this->statusCode,
			$this->representation,
			$allHeaders
		);
	}

	/**
	 * @param Representation $representation
	 * @return static
	 */
	public static function createSuccess(Representation $representation): self
	{
		return (new self())
			->withStatusCode(RESTClient::HTTP_OK)
			->withRepresentation($representation);
	}

	/**
	 * @return static
	 */
	public static function createNoContent(): self
	{
		return (new self())
			->withStatusCode(RESTClient::HTTP_NO_CONTENT);
	}
}

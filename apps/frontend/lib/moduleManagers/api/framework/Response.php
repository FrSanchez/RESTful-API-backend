<?php
namespace Api\Framework;

use Api\Representations\Representation;

/**
 * Represents a response for the API. This should be immutable.
 *
 * Class Response
 * @package Api\Framework
 */
class Response
{
	/** @var int */
	private $statusCode;

	/** @var Representation|null */
	private $representation;

	/** @var array */
	private $headers;

	public function __construct(int $statusCode, ?Representation $representation, array $headers)
	{
		$this->statusCode = $statusCode;
		$this->representation = $representation;
		$this->headers = $headers;
	}

	/**
	 * @return int
	 */
	public function getStatusCode(): int
	{
		return $this->statusCode;
	}

	/**
	 * @return Representation|null
	 */
	public function getRepresentation(): ?Representation
	{
		return $this->representation;
	}

	public function getHeaderNames(): array
	{
		return array_keys($this->headers);
	}

	public function getHeaderValueByName(string $name): string
	{
		return $this->headers[$name];
	}
}

<?php

namespace Api\Objects\Postman;

use JsonSerializable;

class Request implements JsonSerializable
{
	/** @var string */
	private string $method;
	/** @var Entry[] */
	private array $header;
	/** @var Body */
	private Body $body;
	/** @var Url */
	private Url $url;

	public function __construct()
	{
		$this->header = [];
		$this->body = new Body();
		$this->url = new Url();
	}

	/**
	 * @return Url
	 */
	public function getUrl(): Url
	{
		return $this->url;
	}

	/**
	 * @param Url $url
	 */
	public function setUrl(Url $url)
	{
		$this->url = $url;
	}

	/**
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * @param string $method
	 */
	public function setMethod(string $method)
	{
		$this->method = $method;
	}

	/**
	 * @return Entry[]
	 */
	public function getHeader(): array
	{
		return $this->header;
	}

	/**
	 * @param Entry[] $header
	 */
	public function setHeader(array $header)
	{
		$this->header = $header;
	}

	public function addHeader(Entry $header)
	{
		$this->header[] = $header;
	}

	/**
	 * @return Body
	 */
	public function getBody(): Body
	{
		return $this->body;
	}

	/**
	 * @param Body $body
	 */
	public function setBody(Body $body)
	{
		$this->body = $body;
	}


	public function jsonSerialize()
	{
		return get_object_vars($this);
	}
}

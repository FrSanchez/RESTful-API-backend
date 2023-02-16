<?php

namespace Api\Objects\Postman;

use JsonSerializable;

class Url implements JsonSerializable
{
	/** @var string */
	private $raw;
	/** @var string */
	private $protocol;
	/** @var string[] */
	private $host;
	/** @var string[] */
	private $path;
	/** @var Entry[] */
	private $query;
	/** @var Entry[] */
	private $variable;

	public function __construct()
	{
		$this->host = [];
		$this->path = [];
		$this->query = [];
		$this->variable = [];
	}

	/**
	 * @return string
	 */
	public function getRaw(): string
	{
		return $this->raw;
	}

	/**
	 * @return string
	 */
	public function getProtocol(): string
	{
		return $this->protocol;
	}

	/**
	 * @param string $protocol
	 */
	public function setProtocol(string $protocol)
	{
		$this->protocol = $protocol;
		$this->updateRaw();
	}

	public function updateRaw()
	{
		$this->raw = "{$this->protocol}://";
		if (isset($this->host[0])) {
			$this->raw .= "{$this->host[0]}";
		}
		if (!empty($this->path)) {
			$path = join("/", $this->path);
			$this->raw .= "/{$path}";
		}
		if (!empty($this->query)) {
			$parts = [];
			foreach ($this->query as $query) {
				if (is_bool($query->isDisabled()) && !$query->isDisabled()) {
					$parts[] = "{$query->getKey()}={$query->getValue()}";
				}
			}
			if (!empty($parts)) {
				$query = join("&", $parts);
				$this->raw .= "?{$query}";
			}
		}
	}

	/**
	 * @return string[]
	 */
	public function getHost(): array
	{
		return $this->host;
	}

	/**
	 * @param string[] $host
	 * @return Url
	 */
	public function setHost(array $host)
	{
		$this->host = $host;
		$this->updateRaw();
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getPath(): array
	{
		return $this->path;
	}

	/**
	 * @param string[] $path
	 */
	public function setPath(array $path)
	{
		$this->path = $path;
		$this->updateRaw();
	}

	/**
	 * @return Entry[]
	 */
	public function getQuery(): array
	{
		return $this->query;
	}

	public function addQuery(Entry $entry)
	{
		$this->query[] = $entry;
		$this->updateRaw();
	}

	/**
	 * @return Entry[]
	 */
	public function getVariable(): array
	{
		return $this->variable;
	}

	/**
	 * @param Entry $variable
	 * @return Url
	 */
	public function addVariable(Entry $variable)
	{
		$this->variable[] = $variable;
		return $this;
	}

	public function jsonSerialize()
	{
		return array_filter(get_object_vars($this));
	}
}

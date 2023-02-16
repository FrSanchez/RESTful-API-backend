<?php


namespace Api\Objects\Postman;

use JsonSerializable;

class Body implements JsonSerializable
{
	private ?string $mode;
	/** @var Entry[] */
	private array $formdata;
	private ?string $raw;
	/** @var null|array */
	private $options;

	public function __construct()
	{
		$this->formdata = [];
		$this->raw = null;
		$this->options = null;
	}

	/**
	 * @return string|null
	 */
	public function getRaw(): ?string
	{
		return $this->raw;
	}

	/**
	 * @param string|null $raw
	 * @return void
	 */
	public function setRaw(?string $raw)
	{
		$this->raw = $raw;
	}

	/**
	 * @return mixed
	 */
	public function getOptions()
	{
		return $this->options;
	}

	/**
	 * @param mixed $options
	 * @return void
	 */
	public function setOptions($options)
	{
		$this->options = $options;
	}


	/**
	 * @return string
	 */
	public function getMode(): string
	{
		return $this->mode;
	}

	/**
	 * @param string $mode
	 * @return Body
	 */
	public function setMode(string $mode)
	{
		$this->mode = $mode;
		return $this;
	}

	/**
	 * @return Entry[]
	 */
	public function getFormdata(): array
	{
		return $this->formdata;
	}

	/**
	 * @param Entry[] $formdata
	 * @return Body
	 */
	public function setFormdata(array $formdata)
	{
		$this->formdata = $formdata;
		return $this;
	}

	public function jsonSerialize()
	{
		return array_filter(get_object_vars($this));
	}
}

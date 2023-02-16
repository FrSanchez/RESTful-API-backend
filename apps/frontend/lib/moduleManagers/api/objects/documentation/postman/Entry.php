<?php

namespace Api\Objects\Postman;

use JsonSerializable;

class Entry implements JsonSerializable
{
	/** @var string */
	private $key;
	/** @var string */
	private $type;
	/** @var string|null */
	private $value;
	/** @var bool|null */
	private $disabled;
	/** @var string */
	private $description;
	/** @var string */
	private $src;

	public function __construct($key = null, $type = null, $value = null, $description = null, $disabled = null, $src = null)
	{
		$this->key = $key;
		$this->type = $type;
		$this->value = $value;
		$this->description = $description;
		$this->disabled = $disabled;
		$this->src = $src;
	}

	/**
	 * @return string
	 */
	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function setKey(string $key)
	{
		$this->key = $key;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 * @return void
	 */
	public function setType(string $type)
	{
		$this->type = $type;
	}

	/**
	 * @return string
	 */
	public function getValue(): string
	{
		return $this->value;
	}

	/**
	 * @param string $value
	 * @return void
	 */
	public function setValue(string $value)
	{
		$this->value = $value;
	}

	/**
	 * @param bool $disabled
	 * @return void
	 */
	public function setDisabled(bool $disabled)
	{
		$this->disabled = $disabled;
	}

	/**
	 * @return string
	 */
	public function getSrc(): string
	{
		return $this->src;
	}

	/**
	 * @param string $src
	 * @return void
	 */
	public function setSrc(string $src)
	{
		$this->src = $src;
	}

	public function jsonSerialize()
	{
		$arr = array_filter(get_object_vars($this));
		if (isset($this->disabled) && !$this->isDisabled()) {
			unset($arr['disabled']);
		}
		return $arr;
	}

	/**
	 * @return bool|null
	 */
	public function isDisabled(): ?bool
	{
		return $this->disabled;
	}
}

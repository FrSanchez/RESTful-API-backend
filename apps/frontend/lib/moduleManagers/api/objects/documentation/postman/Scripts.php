<?php


namespace Api\Objects\Postman;

use JsonSerializable;

class Script implements JsonSerializable
{
	/** @var string[] */
	private array $exec;
	/** @var string */
	private string $type;

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 * @return Script
	 */
	public function setType(string $type)
	{
		$this->type = $type;
	}

	/**
	 * @return string[]
	 */
	public function getExec(): array
	{
		return $this->exec;
	}

	/**
	 * @param string[] $exec
	 */
	public function setExec(array $exec)
	{
		$this->exec = $exec;
	}


	public function jsonSerialize()
	{
		return array_filter(get_object_vars($this));
	}
}

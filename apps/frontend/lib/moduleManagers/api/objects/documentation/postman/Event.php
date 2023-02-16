<?php


namespace Api\Objects\Postman;

use JsonSerializable;

class Event implements JsonSerializable
{
	/** @var string|null */
	private ?string $listen;
	/** @var Script|null */
	private ?Script $script;

	public function __construct()
	{
		$this->listen = "";
		$this->script = new Script();
	}

	/**
	 * @return string|null
	 */
	public function getListen(): ?string
	{
		return $this->listen;
	}

	/**
	 * @param string|null $listen
	 * @return void
	 */
	public function setListen(?string $listen)
	{
		$this->listen = $listen;
	}

	/**
	 * @return Script|null
	 */
	public function getScript(): ?Script
	{
		return $this->script;
	}

	/**
	 * @param Script|null $script
	 * @return void
	 */
	public function setScript(?Script $script)
	{
		$this->script = $script;
	}

	public function jsonSerialize()
	{
		return array_filter(get_object_vars($this));
	}
}

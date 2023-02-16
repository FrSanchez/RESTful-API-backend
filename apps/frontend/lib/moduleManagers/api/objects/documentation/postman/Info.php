<?php

namespace Api\Objects\Postman;

use Exception;
use JsonSerializable;

class Info implements JsonSerializable
{
	private string $_postman_id;
	private string $name;
	private string $schema;
	private string $description;

	/**
	 * Info constructor.
	 * @param string $name
	 * @param string $description
	 * @param string $schema
	 * @throws Exception
	 */
	public function __construct(string $postmanId, string $name, string $description, string $schema)
	{
		$this->_postman_id = $postmanId;
		$this->name = $name;
		$this->description = $description;
		$this->schema = $schema;
	}

	/**
	 * @return array|mixed
	 */
	public function jsonSerialize()
	{
		return array_filter(get_object_vars($this));
	}
}

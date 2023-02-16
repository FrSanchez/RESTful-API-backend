<?php
namespace Api\Framework;

use Api\DataTypes\DataType;

class ApiParameter
{
	/** @var DataType */
	private $type;

	/** @var string  */
	private $rawValue;

	/** @var mixed */
	private $serverValue;

	public function __construct(DataType $type, string $rawValue, $serverValue)
	{
		$this->type = $type;
		$this->rawValue = $rawValue;
		$this->serverValue = $serverValue;
	}

	/**
	 * @return DataType
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @return string
	 */
	public function getRawValue()
	{
		return $this->rawValue;
	}

	/**
	 * @return mixed
	 */
	public function getServerValue()
	{
		return $this->serverValue;
	}
}

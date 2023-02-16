<?php

namespace Api\Representations;

use Api\DataTypes\DataType;

class RepresentationPropertyDefinition
{
	private string $name;
	private DataType $dataType;
	private bool $readable;
	private bool $writeable;
	private bool $required;

	public function __construct(string $name, DataType $dataType, bool $readable = false, bool $writeable = false, ?bool $required = false)
	{
		$this->name = $name;
		$this->dataType = $dataType;
		$this->readable = $readable;
		$this->writeable = $writeable;
		$this->required = is_null($required) ? false : $required;
	}

	/**
	 * @return bool
	 */
	public function isRequired(): bool
	{
		return $this->required;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return DataType
	 */
	public function getDataType(): DataType
	{
		return $this->dataType;
	}

	/**
	 * True if the property can be written to (e.g. input by the user in a request body).
	 * @return bool
	 */
	public function isWriteable(): bool
	{
		return $this->writeable;
	}

	/**
	 * True if the property can be read by the user (e.g. returned to the user in a response).
	 * @return bool
	 */
	public function isReadable(): bool
	{
		return $this->readable;
	}
}

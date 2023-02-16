<?php

namespace Api\Representations;

use Api\DataTypes\DataType;
use Api\Exceptions\ApiException;
use Api\Yaml\YamlObject;
use stdClass;

class PolymorphicRepresentation
{
	private string $discriminator;
	/** @var DataType[] */
	private array $dataTypes;
	private stdClass $exception;

	public function __construct(array $dataTypes, string $discriminator, $exception)
	{
		$this->dataTypes = $dataTypes;
		$this->discriminator = $discriminator;
		$this->exception = $exception;
	}

	/**
	 * @return YamlObject
	 */
	public function getException(): stdClass
	{
		return $this->exception;
	}

	/**
	 * @return string
	 */
	public function getDiscriminator(): string
	{
		return $this->discriminator;
	}

	/**
	 * @return DataType[]
	 */
	public function getDataTypes(): array
	{
		return $this->dataTypes;
	}
}

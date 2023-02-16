<?php

namespace Api\Objects\Relationships;

use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;

class RelationshipReferenceToDefinition
{
	/** @var string $objectName */
	private $objectName;

	/** @var string $fieldName */
	private $fieldName;

	public function __construct(string $objectName, string $fieldName)
	{
		$this->objectName = $objectName;
		$this->fieldName = $fieldName;
	}

	/**
	 * @return string
	 */
	public function getObjectName(): string
	{
		return $this->objectName;
	}

	/**
	 * @return string
	 */
	public function getFieldName(): string
	{
		return $this->fieldName;
	}
}

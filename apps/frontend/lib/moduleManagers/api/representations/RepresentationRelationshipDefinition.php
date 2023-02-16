<?php
namespace Api\Representations;

class RepresentationRelationshipDefinition
{
	/** @var string $name */
	private $name;

	/** @var string $objectName */
	private $objectName;

	/**
	 * RepresentationRelationshipDefinition constructor.
	 * @param string $name
	 * @param string $objectName
	 */
	public function __construct(string $name, string $objectName)
	{
		$this->name = $name;
		$this->objectName = $objectName;
	}

	/**
	 * @return string
	 */
	public function getName() : string
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getObjectName() : string
	{
		return $this->objectName;
	}
}

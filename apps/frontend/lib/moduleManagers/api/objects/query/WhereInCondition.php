<?php

namespace Api\Objects\Query;

use Api\Objects\FieldDefinition;
use Doctrine_Query;
use Doctrine_Query_Exception;

class WhereInCondition implements WhereCondition
{
	private FieldDefinition $fieldDefinition;
	private array $values;

	function __construct(FieldDefinition $fieldDefinition, array $values)
	{
		$this->fieldDefinition = $fieldDefinition;
		$this->values = $values;
	}

	/**
	 * @param Doctrine_Query $doctrineQuery
	 * @throws Doctrine_Query_Exception
	 */
	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery): void
	{
		$doctrineQuery->andWhereIn($this->fieldDefinition->getDoctrineField(), $this->values);
	}

	public function toDql(): string
	{
		$valuesString = implode(",", $this->values);
		return $this->fieldDefinition->getDoctrineField() . " IN ($valuesString)";
	}

	public function getParameters(): array
	{
		return $this->values;
	}
}

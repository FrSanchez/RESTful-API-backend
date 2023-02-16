<?php
namespace Api\Objects\Query;

use Api\Objects\FieldDefinition;
use Doctrine_Query;

class IsNotNullWhereCondition implements WhereCondition
{
	private FieldDefinition $fieldDefinition;

	function __construct(FieldDefinition $fieldDefinition)
	{
		$this->fieldDefinition = $fieldDefinition;
	}

	/**
	 * @param Doctrine_Query $doctrineQuery
	 */
	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery): void
	{
		$doctrineQuery->addWhere($this->toDql());
	}

	public function toDql(): string
	{
		return $this->fieldDefinition->getDoctrineField() . ' is not NULL';
	}

	public function getParameters(): array
	{
		return [];
	}
}

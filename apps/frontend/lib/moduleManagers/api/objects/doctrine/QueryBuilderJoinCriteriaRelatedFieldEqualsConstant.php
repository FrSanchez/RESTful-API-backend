<?php


namespace Api\Objects\Doctrine;


class QueryBuilderJoinCriteriaRelatedFieldEqualsConstant implements QueryBuilderJoinCriteria
{
	private $field;
	private $value;
	private $usePrimaryField;

	public function __construct(string $field, $value)
	{
		$this->field = $field;
		$this->value = $value;
	}

	public function buildClause(string $primaryAlias, $relationshipAlias) {
		return "{$relationshipAlias}.{$this->field} = {$this->value}";
	}
}

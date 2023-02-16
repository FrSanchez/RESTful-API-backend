<?php
namespace Api\Objects\Query;

use Api\Objects\Collections\CollectionSelection;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Relationships\RelationshipSelection;

/**
 * Abstract query that object queries are based on.
 *
 * Class AbstractQuery
 * @package Api\Objects\Query
 */
abstract class AbstractQuery
{
	/** @var int */
	private $accountId;

	/** @var ObjectDefinition */
	private $objectDefinition;

	/** @var string[] */
	private $selection = [];

	/** @var string[] */
	private $selectAdditionalFields = [];

	/** @var WhereCondition[] */
	private $whereConditions = [];

	protected function __construct(int $accountId, ObjectDefinition $objectDefinition)
	{
		$this->accountId = $accountId;
		$this->objectDefinition = $objectDefinition;
	}

	public function getAccountId(): int
	{
		return $this->accountId;
	}

	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * @param FieldDefinition|FieldSelection|RelationshipSelection|CollectionSelection ...$selections
	 * @return $this
	 */
	public function addSelections(...$selections): self
	{
		for ($i = 0; $i < count($selections); $i++) {
			if ($selections[$i] instanceof FieldDefinition) {
				$selections[$i] = new FieldScalarSelection($selections[$i]);
			}
		}
		$this->selection = array_merge($this->selection, $selections);
		return $this;
	}

	/**
	 * @return FieldSelection[]|RelationshipSelection[]|CollectionSelection[]
	 */
	public function getSelection(): array
	{
		return $this->selection;
	}

	public function addWhereEquals(string $fieldName, $value): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(OperatorWhereCondition::equalsTo($fieldDefinition, $value));
	}

	public function addWhereGreaterThan(string $fieldName, $value): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(OperatorWhereCondition::greaterThan($fieldDefinition, $value));
	}

	public function addWhereGreaterThanOrEqualTo(string $fieldName, $value): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(OperatorWhereCondition::greaterThanOrEqualTo($fieldDefinition, $value));
	}

	public function addWhereLessThan(string $fieldName, $value): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(OperatorWhereCondition::lessThan($fieldDefinition, $value));
	}

	public function addWhereLessThanOrEqualTo(string $fieldName, $value): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(OperatorWhereCondition::lessThanOrEqualTo($fieldDefinition, $value));
	}

	public function addWhereIsNull(string $fieldName): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(new IsNullWhereCondition($fieldDefinition));
	}

	public function addWhereIsNotNull(string $fieldName): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(new IsNotNullWhereCondition($fieldDefinition));
	}

	public function addWhereCondition(WhereCondition $whereCondition): self
	{
		$this->whereConditions[] = $whereCondition;
		return $this;
	}

	public function addWhereInCondition(string $fieldName, array $values): self
	{
		$fieldDefinition = $this->getFieldDefinitionForWhereOrThrow($fieldName);
		return $this->addWhereCondition(new WhereInCondition($fieldDefinition, $values));
	}

	/**
	 * @return WhereCondition[]
	 */
	public function getWhereConditions(): array
	{
		return $this->whereConditions;
	}

	/**
	 * Adds additional fields to be returned that are not returned in the representation. This is useful for
	 * adding fields to the query that are needed for processing (like ID or updated_at) when they may not have
	 * been selected by the user.
	 *
	 * @param string[] $fieldNames
	 * @return $this
	 */
	public function addSelectAdditionalFields(string...$fieldNames): self
	{
		$this->selectAdditionalFields = array_merge($this->selectAdditionalFields, $fieldNames);
		return $this;
	}

	/**
	 * @return string[]
	 */
	public function getSelectAdditionalFields(): array
	{
		return $this->selectAdditionalFields;
	}

	/**
	 * Gets the number of results to be returned when this query is executed.
	 * @return int
	 */
	public abstract function getLimit(): int;

	/**
	 * Gets the index of the first row to return. This should be zero-index based.
	 * @return int
	 */
	public abstract function getOffset(): int;

	private function getFieldDefinitionForWhereOrThrow(string $fieldName): FieldDefinition
	{
		$fieldDefinition = $this->objectDefinition->getFieldByName($fieldName);
		if (!$fieldDefinition) {
			throw new ObjectQueryException("Unknown field specified in WHERE clause: {$fieldName}.");
		}
		return $fieldDefinition;
	}
}

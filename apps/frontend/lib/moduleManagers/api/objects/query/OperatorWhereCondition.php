<?php
namespace Api\Objects\Query;

use Api\Objects\FieldDefinition;
use DateTime;
use dateTools;
use Doctrine_Query;

class OperatorWhereCondition implements WhereCondition
{
	protected FieldDefinition $fieldDefinition;
	protected string $operator;

	/** @var mixed|null */
	protected $serverValue;

	private function __construct(FieldDefinition $fieldDefinition, string $operator, $serverValue)
	{
		$this->fieldDefinition = $fieldDefinition;
		$this->operator = $operator;
		$this->serverValue = $serverValue;
	}

	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery): void
	{
		$dbValue = ServerToDatabaseValueConverter::getInstance()->convertServerValueToDatabaseValue($this->serverValue);
		$doctrineQuery->addWhere(
			$this->toDql(),
			$dbValue
		);
	}

	public function toDql(): string
	{
		return $this->fieldDefinition->getDoctrineField() . ' ' . $this->operator . ' ?';
	}

	public function getParameters(): array
	{
		return [
			$this->serverValue
		];
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @param mixed $serverValue
	 * @return OperatorWhereCondition
	 */
	public static function equalsTo(FieldDefinition $fieldDefinition, $serverValue): OperatorWhereCondition
	{
		return new self($fieldDefinition, '=', $serverValue);
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @param mixed $serverValue
	 * @return OperatorWhereCondition
	 */
	public static function greaterThan(FieldDefinition $fieldDefinition, $serverValue): OperatorWhereCondition
	{
		return new self($fieldDefinition, '>', $serverValue);
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @param mixed $serverValue
	 * @return OperatorWhereCondition
	 */
	public static function greaterThanOrEqualTo(FieldDefinition $fieldDefinition, $serverValue): OperatorWhereCondition
	{
		return new self($fieldDefinition, '>=', $serverValue);
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @param mixed $serverValue
	 * @return OperatorWhereCondition
	 */
	public static function lessThan(FieldDefinition $fieldDefinition, $serverValue): OperatorWhereCondition
	{
		return new self($fieldDefinition, '<', $serverValue);
	}

	/**
	 * @param FieldDefinition $fieldDefinition
	 * @param mixed $serverValue
	 * @return OperatorWhereCondition
	 */
	public static function lessThanOrEqualTo(FieldDefinition $fieldDefinition, $serverValue): OperatorWhereCondition
	{
		return new self($fieldDefinition, '<=', $serverValue);
	}
}

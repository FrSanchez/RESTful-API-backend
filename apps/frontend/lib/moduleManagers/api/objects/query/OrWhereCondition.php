<?php
namespace Api\Objects\Query;

use Doctrine_Query;

class OrWhereCondition implements WhereCondition
{
	/** @var WhereCondition[] */
	private array $childConditions;

	/**
	 * @param WhereCondition[] $childConditions
	 */
	public function __construct(array $childConditions)
	{
		$this->childConditions = $childConditions;
	}

	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery): void
	{
		$serverParameters = $this->getParameters();
		$serverToDatabaseValueConverter = ServerToDatabaseValueConverter::getInstance();
		$dbParameters = [];
		foreach ($serverParameters as $serverParameter) {
			$dbParameters[] = $serverToDatabaseValueConverter->convertServerValueToDatabaseValue($serverParameter);
		}
		$doctrineQuery->addWhere($this->toDql(), $dbParameters);
	}

	public function toDql(): string
	{
		$childDqls = [];
		foreach ($this->childConditions as $childCondition) {
			$childDqls[] = $childCondition->toDql();
		}
		return '(' . join(' OR ', $childDqls) . ')';
	}

	public function getParameters(): array
	{
		$allParameters = [];
		foreach ($this->childConditions as $childCondition) {
			$allParameters = array_merge($allParameters, $childCondition->getParameters());
		}
		return $allParameters;
	}
}

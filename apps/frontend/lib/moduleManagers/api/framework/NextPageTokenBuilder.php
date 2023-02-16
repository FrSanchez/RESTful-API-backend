<?php

namespace Api\Framework;

use Api\DataTypes\ConversionContext;
use Api\DataTypes\DateTimeDataType;
use Api\Objects\Query\OperatorWhereCondition;
use Api\Objects\Query\OrderByPair;
use DateTime;

class NextPageTokenBuilder
{
	public const TTL = "+4 hours";

	/** @var OrderByPair[] */
	private array $orderBy;
	/** @var OperatorWhereCondition[] */
	private array $filters = [];
	private int $limit;
	private int $page;
	private int $recCount;
	private $lastRecord = null;
	/** @var string|bool|null  */
	private $deleted = null;
	private ConversionContext $context;

	public function __construct(ConversionContext $context)
	{
		$this->context = $context;
	}

	/**
	 * @param OrderByPair[] $orderBy
	 * @return NextPageTokenBuilder
	 */
	public function setOrderBy(array $orderBy): NextPageTokenBuilder
	{
		$this->orderBy = $orderBy;
		return $this;
	}

	/**
	 * @param ApiParameter[] $filters
	 * @return NextPageTokenBuilder
	 */
	public function setFilters(array $filters): NextPageTokenBuilder
	{
		foreach ($filters as $parameterName => $apiParameter) {
			$this->filters[$parameterName] = $apiParameter->getRawValue();
		}

		return $this;
	}

	/**
	 * @param mixed $limit
	 * @return NextPageTokenBuilder
	 */
	public function setLimit(int $limit): NextPageTokenBuilder
	{
		$this->limit = $limit;
		return $this;
	}

	public function setLastRecord($lastRecord)
	{
		$this->lastRecord = $lastRecord;
		return $this;
	}

	/**
	 * @param int $page
	 * @return NextPageTokenBuilder
	 */
	public function setPage(int $page): NextPageTokenBuilder
	{
		$this->page = $page;
		return $this;
	}

	/**
	 * @param int $page
	 * @return NextPageTokenBuilder
	 */
	public function setRecCount(int $recCount): NextPageTokenBuilder
	{
		$this->recCount = $recCount;
		return $this;
	}

	/**
	 * @return string
	 */
	private function buildOrderBy(): string
	{
		$orderBy = [];
		foreach ($this->orderBy as $pair) {
			$orderBy[] = "{$pair}";
		}
		return join(',', $orderBy);
	}

	/**
	 * @param array $lastRecord
	 * @return ResumeValue
	 */
	private function buildResumeValue(array $lastRecord): ResumeValue
	{
		return ResumeValue::buildResumeValue($lastRecord, $this->orderBy, $this->context);
	}

	/**
	 * @return NextPageToken|false
	 */
	public function build()
	{
		$nextWeek = new DateTime(self::TTL);
		$expiration = DateTimeDataType::getInstance()->convertServerValueToApiValue($nextWeek, $this->context);
		if (is_null($this->lastRecord) || empty($this->lastRecord)) {
			return false;
		} else {
			$resumeValue = $this->buildResumeValue($this->lastRecord);
		}
		return new NextPageToken($this->buildOrderBy(), $this->limit, $this->page, $this->filters, $resumeValue, $this->recCount, $expiration, $this->deleted);
	}

	public function setDeleted($deleted)
	{
		$this->deleted = $deleted;
		return $this;
	}
}

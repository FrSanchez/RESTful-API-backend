<?php

namespace Api\Framework;

use JsonSerializable;
use ReturnTypeWillChange;

class NextPageToken implements JsonSerializable
{
	private string $orderBy;
	/** @var string[] */
	private array $filters;
	private int $limit;
	private ResumeValue $resumeValue;
	private int $page;
	private int $recCount;
	private string $expireTime;
	private $deleted;

	/**
	 * NextPageToken constructor.
	 * @param string $orderBy
	 * @param int $limit
	 * @param int $page
	 * @param array $filters
	 * @param ResumeValue $resumeValue
	 * @param string $expiration
	 * @param mixed $deleted
	 */
	public function __construct(string $orderBy, int $limit, int $page, array $filters, ResumeValue $resumeValue, int $recCount, string $expiration, $deleted = null)
	{
		$this->orderBy = $orderBy;
		$this->filters = $filters;
		$this->limit = $limit;
		$this->resumeValue = $resumeValue;
		$this->page = $page;
		$this->recCount = $recCount;
		$this->expireTime = $expiration;
		$this->deleted = $deleted;
	}

	/**
	 * @return string
	 */
	public function getExpireTime(): string
	{
		return $this->expireTime;
	}

	/**
	 * @return string
	 */
	public function getOrderBy(): string
	{
		return $this->orderBy;
	}

	/**
	 * @return string[]
	 */
	public function getFilters(): array
	{
		return $this->filters;
	}

	/**
	 * @return int
	 */
	public function getLimit(): int
	{
		return $this->limit;
	}

	/**
	 * @return ResumeValue
	 */
	public function getResumeValue(): ResumeValue
	{
		return $this->resumeValue;
	}

	/**
	 * @return int
	 */
	public function getPage(): int
	{
		return $this->page;
	}

	/**
	 * @return int
	 */
	public function getRecCount(): int
	{
		return $this->recCount;
	}

	/**
	 * Used by internal json serialization to obtain the list of non-public fields that will get serialized
	 * @inheritDoc
	 * @PHP8Upgrade Fix return type
	 */
	#[ReturnTypeWillChange]
	public function jsonSerialize()
	{
		return get_object_vars($this);
	}

	/**
	 * Returns the JSON representation for this token
	 * @return false|string
	 */
	public function toJSON()
	{
		return json_encode($this);
	}

	/**
	 * Returns the base64 encoded string from the json representation
	 * @return string
	 */
	public function encode(): string
	{
		return base64_encode($this->toJSON());
	}
}

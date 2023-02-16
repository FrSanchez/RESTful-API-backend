<?php
namespace Api\Framework;

class ApiObjectsRecordActionPathParameters
{
	private int $version;
	private string $urlObjectName;
	private array $primaryKey;
	private string $actionName;

	public function __construct(int $version, string $urlObjectName, array $primaryKey, string $actionName)
	{
		$this->version = $version;
		$this->urlObjectName = $urlObjectName;
		$this->primaryKey = $primaryKey;
		$this->actionName = $actionName;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * @return string
	 */
	public function getUrlObjectName(): string
	{
		return $this->urlObjectName;
	}

	/**
	 * @return array
	 */
	public function getPrimaryKey(): array
	{
		return $this->primaryKey;
	}

	/**
	 * @return string
	 */
	public function getActionName(): string
	{
		return $this->actionName;
	}
}

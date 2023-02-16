<?php
namespace Api\Framework;

class ApiObjectsIdPathParameters
{
	private int $version;
	private string $urlObjectName;
	private array $primaryKey;

	public function __construct(int $version, string $urlObjectName, array $primaryKey)
	{
		$this->version = $version;
		$this->urlObjectName = $urlObjectName;
		$this->primaryKey = $primaryKey;
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
}

<?php
namespace Api\Framework;

class ApiObjectsPathParameters
{
	private int $version;
	private string $urlObjectName;

	public function __construct(int $version, string $urlObjectName)
	{
		$this->version = $version;
		$this->urlObjectName = $urlObjectName;
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
}

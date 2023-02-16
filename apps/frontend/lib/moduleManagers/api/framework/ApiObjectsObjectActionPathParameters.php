<?php
namespace Api\Framework;

/**
 * Path parameters for the Object Action endpoints
 */
class ApiObjectsObjectActionPathParameters
{
	private int $version;
	private string $urlObjectName;
	private string $actionName;

	public function __construct(int $version, string $urlObjectName, string $actionName)
	{
		$this->version = $version;
		$this->urlObjectName = $urlObjectName;
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
	 * @return string
	 */
	public function getActionName(): string
	{
		return $this->actionName;
	}
}

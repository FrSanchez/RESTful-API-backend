<?php


namespace Api\Representations;

use Api\Objects\ObjectDefinitionCatalog;

/**
 * Information about the system state when building representations.
 *
 * Class RepresentationBuilderContext
 * @package Api\Representations
 */
class RepresentationBuilderContext
{
	/** @var int $accountId */
	private $accountId;

	/** @var int $version */
	private $version;

	public function __construct(int $accountId, int $version)
	{
		$this->accountId = $accountId;
		$this->version = $version;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Just to help with dependency injection in tests.
	 * @return ObjectDefinitionCatalog
	 * @throws \Exception
	 */
	public function getObjectDefinitionCatalog(): ObjectDefinitionCatalog
	{
		return ObjectDefinitionCatalog::getInstance();
	}
}

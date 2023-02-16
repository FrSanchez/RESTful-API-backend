<?php
namespace Api\Objects\Doctrine;

use Api\Framework\FileInput;
use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinition;
use piUser;
use apiActions;
use Api\Representations\Representation;

/**
 * The context in which a record is created.
 *
 * Class DoctrineCreateContext
 * @package Api\Objects\Doctrine
 */
class DoctrineCreateContext
{
	private int $version;
	private AccessContext  $accessContext;
	private ObjectDefinition $objectDefinition;
	private Representation $representation;
	private apiActions $apiActions;
	private ?FileInput $fileInput;

	/**
	 * DoctrineCreateContext constructor.
	 * @param int $version
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param Representation $representation
	 * @param apiActions $apiActions
	 * @param FileInput|null $fileInput
	 */
	public function __construct(
		int $version,
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		Representation $representation,
		apiActions $apiActions,
		?FileInput $fileInput = null
	) {
		$this->version = $version;
		$this->accessContext = $accessContext;
		$this->objectDefinition = $objectDefinition;
		$this->representation = $representation;
		$this->apiActions = $apiActions;
		$this->fileInput = $fileInput;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accessContext->getAccountId();
	}

	/**
	 * @return piUser
	 */
	public function getUser(): piUser
	{
		return $this->accessContext->getUser();
	}

	/**
	 * @return AccessContext
	 */
	public function getAccessContext(): AccessContext
	{
		return $this->accessContext;
	}

	/**
	 * @return ObjectDefinition
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}

	/**
	 * @return Representation
	 */
	public function getRepresentation(): Representation
	{
		return $this->representation;
	}

	/**
	 * @return FileInput|null
	 */
	public function getFileInput(): ?FileInput
	{
		return $this->fileInput;
	}

	/**
	 * @return apiActions
	 */
	public function getApiActions(): apiActions
	{
		return $this->apiActions;
	}
}

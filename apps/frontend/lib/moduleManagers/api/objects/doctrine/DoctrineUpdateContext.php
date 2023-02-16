<?php
namespace Api\Objects\Doctrine;

use Api\Framework\FileInput;
use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinition;
use piUser;
use Api\Representations\Representation;
use Doctrine_Record;
use apiActions;

/**
 * The context in which a record is created.
 *
 * Class DoctrineUpdateContext
 * @package Api\Objects\Doctrine
 */
class DoctrineUpdateContext
{
	private int $version;
	private AccessContext  $accessContext;
	private ObjectDefinition $objectDefinition;
	private ?Representation $representation;
	private Doctrine_Record $doctrineRecord;
	private apiActions $apiActions;
	private ?FileInput $fileInput = null;

	/**
	 * DoctrineUpdateContext constructor.
	 * @param int $version
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param Representation|null $representation
	 * @param Doctrine_Record $doctrineRecord
	 * @param apiActions $apiActions
	 * @param FileInput|null $fileInput
	 */
	public function __construct(
		int $version,
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		?Representation $representation,
		Doctrine_Record $doctrineRecord,
		apiActions $apiActions,
		?FileInput $fileInput = null
	) {
		$this->version = $version;
		$this->accessContext = $accessContext;
		$this->objectDefinition = $objectDefinition;
		$this->representation = $representation;
		$this->doctrineRecord = $doctrineRecord;
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
	 * @return Representation|null
	 */
	public function getRepresentation(): ?Representation
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
	 * @return Doctrine_Record
	 */
	public function getDoctrineRecord(): Doctrine_Record
	{
		return $this->doctrineRecord;
	}

	/**
	 * @return apiActions
	 */
	public function getApiActions(): apiActions
	{
		return $this->apiActions;
	}
}

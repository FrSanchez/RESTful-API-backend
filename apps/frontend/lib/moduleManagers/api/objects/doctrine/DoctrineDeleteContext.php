<?php
namespace Api\Objects\Doctrine;

use Api\Objects\Access\AccessContext;
use Api\Objects\ObjectDefinition;
use piUser;
use Doctrine_Record;
use apiActions;

/**
 * The context in which a record is created.
 *
 * Class DoctrineDeleteContext
 * @package Api\Objects\Doctrine
 */
class DoctrineDeleteContext
{
	private int $version;
	private AccessContext  $accessContext;
	private ObjectDefinition $objectDefinition;
	private Doctrine_Record $doctrineRecord;
	private apiActions $apiActions;

	/**
	 * DoctrineDeleteContext constructor.
	 * @param int $version
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param Doctrine_Record $doctrineRecord
	 * @param apiActions $apiActions
	 */
	public function __construct(
		int $version,
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		Doctrine_Record $doctrineRecord,
		apiActions $apiActions
	) {
		$this->version = $version;
		$this->accessContext = $accessContext;
		$this->objectDefinition = $objectDefinition;
		$this->doctrineRecord = $doctrineRecord;
		$this->apiActions = $apiActions;
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

<?php
namespace Api\Config\Objects\TaggedObject\Gen\ExportProcedures;

use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Export\ProcedureDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;
use Exception;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
abstract class AbstractTaggedObjectFilterByProspectUpdatedAtProcedure implements \Api\Config\Objects\TaggedObject\Gen\ExportProcedures\TaggedObjectFilterByProspectUpdatedAtProcedureInterface
{
	/** @var int $accountId */
	private int $accountId;

	/** @var int $version */
	private int $version;

	/** @var array $arguments */
	private array $arguments = [];

	/** @var ProcedureDefinition $procedureDefinition */
	private $procedureDefinition;

	/** @var ObjectDefinition|null $objectDefinition */
	private ?ObjectDefinition $objectDefinition = null;

	/** @var bool $isInternalRequest */
	private bool $isInternalRequest;

	private string $object;

	/**
	 * @param ProcedureDefinition $definition The definition of this procedure.
	 * @param int $version The version of the API
	 * @param int $accountId The account ID that is executing the procedure.
	 * @param array $arguments The arguments specified by the user to be used when executing this procedure.
	 * @param bool $isInternalRequest The request type
	 * @throws Exception
	 */
	public function __construct(ProcedureDefinition $definition, int $version, int $accountId, array $arguments, bool $isInternalRequest, string $object = 'TaggedObject')
	{
		$this->version = $version;
		$this->accountId = $accountId;
		$this->arguments = $arguments;
		$this->procedureDefinition = $definition;
		$this->isInternalRequest = $isInternalRequest;
		$this->object = $object;
		$this->validate($arguments);
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * Gets the object definition for TaggedObject.
	 * @return ObjectDefinition
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		if (!$this->objectDefinition) {
			$this->objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($this->version, $this->accountId, $this->object);
		}
		return $this->objectDefinition;
	}

	/**
	 * @param array $arguments
	 * @throws Exception
	 */
	protected final function validate(array $arguments): void
	{
		$this->validateWithArgs(
			$arguments[self::ARG_PROSPECT_DELETED] ?? null,
			$arguments[self::ARG_PROSPECT_UPDATED_AFTER] ?? null,
			$arguments[self::ARG_PROSPECT_UPDATED_BEFORE] ?? null
		);
	}

	/**
	 * @param bool|string|null $prospectDeleted
	 * @param \DateTime $prospectUpdatedAfter
	 * @param \DateTime|null $prospectUpdatedBefore
	 * @throws Exception
	 */
	public function validateWithArgs(
			 $prospectDeleted,
			\DateTime $prospectUpdatedAfter,
			?\DateTime $prospectUpdatedBefore
	): void
	{
		// override this method to validate the arguments
	}

	/**
	 * @param QueryContext $queryContext Context in which the query is to be executed in.
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @return Doctrine_Query
	 */
	public final function generateDoctrineQuery(QueryContext $queryContext, array $selectedFields): Doctrine_Query
	{
		return $this->generateDoctrineQueryWithArgs(
			$queryContext,
			$selectedFields,
			$this->arguments[self::ARG_PROSPECT_DELETED] ?? null,
			$this->arguments[self::ARG_PROSPECT_UPDATED_AFTER] ?? null,
			$this->arguments[self::ARG_PROSPECT_UPDATED_BEFORE] ?? null
		);
	}

	/**
	 * @param QueryContext $queryContext
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @param bool|string|null $prospectDeleted
	 * @param \DateTime $prospectUpdatedAfter
	 * @param \DateTime|null $prospectUpdatedBefore
	 * @return Doctrine_Query
	 */
	public abstract function generateDoctrineQueryWithArgs(
			QueryContext $queryContext,
			array $selectedFields,
			 $prospectDeleted,
			\DateTime $prospectUpdatedAfter,
			?\DateTime $prospectUpdatedBefore
	): Doctrine_Query;
}

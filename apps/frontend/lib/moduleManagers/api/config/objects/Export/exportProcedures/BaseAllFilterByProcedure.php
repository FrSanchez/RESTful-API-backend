<?php

namespace Api\Config\Objects\Export\ExportProcedures;

use Api\Exceptions\ApiException;
use Api\Export\Procedure;
use Api\Export\ProcedureDefinition;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\QueryContext;
use Api\Objects\StaticObjectDefinitionCatalog;
use ApiErrorLibrary;
use Exception;
use Doctrine_Query;
use RESTClient;

abstract class BaseAllFilterByProcedure implements Procedure
{
	public const ARG_DELETED = "deleted";

	private int $accountId;
	private int $version;
	private array $arguments;
	private ?ObjectDefinition $objectDefinition = null;
	private string $object;
	private bool $supportsDeleted;

	/**
	 * @param ProcedureDefinition $definition The definition of this procedure.
	 * @param int $version The version of the API
	 * @param int $accountId The account ID that is executing the procedure.
	 * @param array $arguments The arguments specified by the user to be used when executing this procedure.
	 * @param bool $isInternalRequest The request type
	 * @throws Exception
	 */
	public function __construct(ProcedureDefinition $definition, int $version, int $accountId, array $arguments, bool $isInternalRequest, string $objectName)
	{
		$objectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($objectName);
		$this->supportsDeleted = $definition->hasArgumentByName(self::ARG_DELETED) && $objectDefinition->isArchivable();
		$this->version = $version;
		$this->accountId = $accountId;
		$this->arguments = $arguments;
		$this->object = $objectName;
		$this->validateRequiredField();
		$this->validateDeletedArgument($arguments);
		$this->validate($arguments);
	}

	/**
	 * @return string
	 */
	abstract public function getName(): string;

	/**
	 * Specifies which database column is required to fulfill this procedure
	 * @return string
	 */
	abstract static public function getRequiredObjectField(): string;

	/**
	 * @param QueryContext $queryContext Context in which the query is to be executed in.
	 * @param FieldDefinition[] $selectedFields The fields that should be returned from this procedure.
	 * @return Doctrine_Query
	 * @throws Exception
	 */
	abstract public function generateDoctrineQuery(QueryContext $queryContext, array $selectedFields): Doctrine_Query;

	/**
	 * Makes sure deleted argument is valid for this object
	 * @param $arguments
	 * @return void
	 * @throws Exception
	 */
	private function validateDeletedArgument($arguments)
	{
		if (!$this->getObjectDefinition()->isArchivable() && isset($arguments[self::ARG_DELETED])) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_ARGUMENT,
				self::ARG_DELETED,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	private function validateRequiredField()
	{
		$field = $this->getObjectDefinition()->getFieldByName($this->getRequiredObjectField());
		if (!$field) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_NAME,
				ucfirst($this->getName()),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param array $arguments
	 * @throws Exception
	 */
	abstract protected function validate(array $arguments): void;

	protected function getSupportsDeleted(): bool
	{
		return $this->supportsDeleted;
	}

	protected function getArguments(): array
	{
		return $this->arguments;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * Gets the object definition for the object.
	 * @return ObjectDefinition
	 * @throws Exception
	 */
	public function getObjectDefinition(): ObjectDefinition
	{
		if (!$this->objectDefinition) {
			$this->objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($this->version, $this->accountId, $this->object);
		}
		return $this->objectDefinition;
	}
}

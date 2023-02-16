<?php
namespace Api\Objects\Query;

use Api\Exceptions\ApiException;
use Api\Objects\Access\AccessContext;
use Api\Objects\Access\ObjectAccessManager;
use Api\Objects\ObjectDefinition;
use Api\Objects\SystemFieldNames;
use ApiErrorLibrary;
use Doctrine_Exception;
use RESTClient;
use RuntimeException;

/**
 * Operations like read, update, delete, and record actions need to verify a single record is active and valid before
 * performing their operations. This class helps facilitate that functionality.
 *
 * Class PreconditionRecordManager
 * @package Api\Framework
 */
class PreconditionRecordManager
{
	private ObjectAccessManager $objectAccessManager;

	/**
	 * @param ObjectAccessManager $objectAccessManager
	 */
	public function __construct(ObjectAccessManager $objectAccessManager)
	{
		$this->objectAccessManager = $objectAccessManager;
	}

	/**
	 * Fetches a "lightweight" record for checking preconditions for loading the full the record and object graph.
	 *
	 * @param QueryContext $queryContext
	 * @param AccessContext $accessContext
	 * @param int $version
	 * @param int $accountId
	 * @param ObjectDefinition $objectDefinition
	 * @param array $primaryKey
	 * @return PreconditionRecord Returns the record (containing server values) if found. If no record is found with the
	 * ID, then an RECORD_NOT_FOUND exception is thrown.
	 * @throws Doctrine_Exception
	 * @throws \Doctrine_Query_Exception
	 */
	public function fetchRecordForPreconditions(
		QueryContext $queryContext,
		ObjectDefinition $objectDefinition,
		array $primaryKey
	): PreconditionRecord {

		// Make sure the user has access to the record
		// Assume all objects have only ID for the primary key
		$this->checkUserCanAccessRecord($queryContext->getAccessContext(), $objectDefinition, $primaryKey[SystemFieldNames::ID]);

		$selectedFields = [];

		$idFieldDefinition = $objectDefinition->getStandardFieldByName(SystemFieldNames::ID);
		if ($idFieldDefinition) {
			$selectedFields[] = $idFieldDefinition;
		} else {
			throw new RuntimeException("Unable to find ID field for object " . $objectDefinition->getType());
		}

		$updatedAtFieldDefinition = $objectDefinition->getStandardFieldByName(SystemFieldNames::UPDATED_AT);
		if ($updatedAtFieldDefinition) {
			$selectedFields[] = $updatedAtFieldDefinition;
		}

		$isDeletedFieldDefinition = $objectDefinition->getStandardFieldByName(SystemFieldNames::IS_DELETED);
		if ($isDeletedFieldDefinition) {
			$selectedFields[] = $isDeletedFieldDefinition;
		}

		$queryModifier = $objectDefinition->getDoctrineQueryModifier();
		$query = $queryModifier->createDoctrineQuery($queryContext, $selectedFields)
			->andWhere('id = ?', $primaryKey[SystemFieldNames::ID]);

		// Make sure that objects where the archivability is not visible to the user act like hard-deleted records
		if ($objectDefinition->isArchivable() &&
			!$objectDefinition->getStandardFieldByName(SystemFieldNames::IS_DELETED)) {
			$query->addWhere('is_archived = ?', false);
		}

		// Using limit(1) instead of fetchOneWithLimit because we need the Doctrine_Collection for the conversion in the
		// next step.
		$doctrineCollection = $query->limit(1)->executeAndFree();
		if (is_null($doctrineCollection) || $doctrineCollection->count() == 0) {
			// If the record doesn't exist or is not valid, then return an empty record. The empty record will be handled
			// in the callers of this function.
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}

		// Transform the record from Doctrine classes to an associative array
		$resultArray = $queryModifier->convertDoctrineCollectionToServerValue(
			$queryContext->getVersion(),
			$doctrineCollection,
			$selectedFields
		)[0];

		return new PreconditionRecord($objectDefinition, $resultArray);
	}

	/**
	 * Verifies that the user can access the given record. If not, an RECORD_NOT_FOUND exception is thrown.
	 * @param AccessContext $accessContext
	 * @param ObjectDefinition $objectDefinition
	 * @param int $recordId
	 * @throws ApiException
	 */
	private function checkUserCanAccessRecord(
		AccessContext $accessContext,
		ObjectDefinition $objectDefinition,
		int $recordId
	): void {
		$hasPermissions = $this->objectAccessManager->canUserAccessRecord(
			$accessContext,
			$objectDefinition,
			$recordId
		);

		if (!$hasPermissions) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}
	}
}

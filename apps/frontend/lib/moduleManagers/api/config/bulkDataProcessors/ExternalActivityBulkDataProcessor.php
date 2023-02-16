<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use ExtensionMetadataDao;
use ExternalActivityTypeMetadataDao;
use MetadataDtoException;
use RetrievableMetadataDao;

class ExternalActivityBulkDataProcessor implements BulkDataProcessor
{
	public const EXTENSION_SELECTION = 'extension';
	public const EXTENSION_SALESFORCE_ID_SELECTION = 'extension_salesforce_id';
	public const EXTENSION_SALESFORCE_ID_SELECTION_CAMEL = 'extensionSalesforceId';
	public const TYPE_SELECTION = 'type';

	public const ALLOWED_OBJECT_FIELDS = [
		self::EXTENSION_SELECTION,
		self::EXTENSION_SALESFORCE_ID_SELECTION,
		self::EXTENSION_SALESFORCE_ID_SELECTION_CAMEL,
		self::TYPE_SELECTION,
	];

	private array $loadedExternalActivityTypes;

	private array $externalActivityTypesToLoad;

	private array $loadedExtensions;

	private array $extensionsToLoad;

	private bool $loadExtensions;

	private ExternalActivityTypeMetadataDao $externalActivityTypeMetadataDao;

	private ExtensionMetadataDao $extensionMetadataDao;

	public function __construct()
	{
		$this->externalActivityTypesToLoad = [];
		$this->extensionsToLoad = [];
		$this->loadedExtensions = [];
		$this->loadedExternalActivityTypes = [];
		$this->loadExtensions = false;
	}

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::EXTERNAL_ACTIVITY_TYPE_FID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if (is_null($doctrineRecord)) {
			return;
		}

		if (!$selection instanceof FieldDefinition
			|| $objectDefinition->getType() !== 'ExternalActivity'
		) {
			throw new \RuntimeException('Only ExternalActivity records may be processed by this bulk processor');
		}

		$externalActivityTypeFid = $doctrineRecord->get(SystemColumnNames::EXTERNAL_ACTIVITY_TYPE_FID);
		if (is_null($externalActivityTypeFid)) {
			return;
		}

		if (in_array($selection->getName(), self::ALLOWED_OBJECT_FIELDS) &&
			!$this->containsLoadedRecordForExternalActivityType($externalActivityTypeFid)
		) {
			$this->externalActivityTypesToLoad[$externalActivityTypeFid] = true;
		}

		if($selection->getName() === self::EXTENSION_SELECTION){
			$this->loadExtensions = true;
		}
	}

	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (empty($this->externalActivityTypesToLoad)) {
			return;
		}

		$externalActivityTypeIdToDtoCollection = $this->retrieveExternalActivityTypMetadataDtos($queryContext);
		foreach (array_keys($this->externalActivityTypesToLoad) as $id) {
			$externalActivityTypeDto = null;
			if(array_key_exists($id, $externalActivityTypeIdToDtoCollection)){
				$externalActivityTypeDto = $externalActivityTypeIdToDtoCollection[$id];
			}
			$this->loadedExternalActivityTypes[$id] = $externalActivityTypeDto;

			if($this->loadExtensions && $externalActivityTypeDto != null &&
				!$this->containsLoadedExtension($externalActivityTypeDto->getExtensionIdentifier())) {
				$this->extensionsToLoad[$externalActivityTypeDto->getExtensionIdentifier()->getLongFormId()] = true;
			}
		}

		if ($this->loadExtensions && count($this->extensionsToLoad)>0) {
			$extensionIdToDtoCollection = $this->retrieveExtensionMetadataDtos($queryContext);
			foreach (array_keys($this->extensionsToLoad) as $id) {
				$extensionDto = null;
				if (array_key_exists($id, $extensionIdToDtoCollection)) {
					$extensionDto = $extensionIdToDtoCollection[$id];
				}
				$this->loadedExtensions[$id] = $extensionDto;

			}
			$this->extensionsToLoad = [];
		}
		$this->externalActivityTypesToLoad = [];
	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}

		$fieldName = $selection->getName();
		if($objectDefinition->getType() != "ExternalActivity") {
			return false;
		}
		$externalActivityTypeFid = $doctrineRecord->get(SystemColumnNames::EXTERNAL_ACTIVITY_TYPE_FID);
		if (is_null($externalActivityTypeFid)) {
			$dbArray[$fieldName] = null;
			return false;
		}
		if (!$this->containsLoadedRecordForExternalActivityType($externalActivityTypeFid)) {
			return true;
		}
		$externalActivityType = $this->loadedExternalActivityTypes[$externalActivityTypeFid];
		if ($externalActivityType == null) {
			$dbArray[$fieldName] = null;
			return false;
		}
		if ($fieldName === self::TYPE_SELECTION) {
			$dbArray[$fieldName] = $externalActivityType->getName();
			return false;
		} elseif ($fieldName === self::EXTENSION_SELECTION) {
			$extensionFid = $externalActivityType->getExtensionIdentifier()->getLongFormId();
			if (!$this->containsLoadedExtension($extensionFid)) {
				return true;
			}

			$extension = $this->loadedExtensions[$extensionFid];
			$dbArray[$fieldName] = ($extension == null) ? null : $extension->getName();
			return false;
		} elseif ($fieldName === self::EXTENSION_SALESFORCE_ID_SELECTION || $fieldName === self::EXTENSION_SALESFORCE_ID_SELECTION_CAMEL) {
			$dbArray[$fieldName] = $externalActivityType->getExtensionIdentifier()->getLongFormId();
		}

		return false;
	}

	/**
	 * @param string $externalActivityTypeFid
	 * @return bool
	 */
	private function containsLoadedRecordForExternalActivityType(string $externalActivityTypeFid): bool
	{
		return array_key_exists($externalActivityTypeFid, $this->loadedExternalActivityTypes);
	}

	/**
	 * @param string $extensionFid
	 * @return bool
	 */
	private function containsLoadedExtension(string $extensionFid): bool
	{
		return array_key_exists($extensionFid, $this->loadedExtensions);
	}

	/**
	 * @param QueryContext $queryContext
	 * @return array
	 * @throws MetadataDtoException
	 */
	private function retrieveExternalActivityTypMetadataDtos(QueryContext $queryContext): array
	{
		$externalActivityTypeMetadataDao = $this->externalActivityTypeMetadataDao?? ExternalActivityTypeMetadataDao::getInstance($queryContext->getAccountId());

		return $this->retrieveIdToDtoCollection($this->externalActivityTypesToLoad, $externalActivityTypeMetadataDao);
	}

	/**
	 * @param QueryContext $queryContext
	 * @return array
	 * @throws MetadataDtoException
	 */
	private function retrieveExtensionMetadataDtos(QueryContext $queryContext)
	{
		$extensionMetadataDao = $this->extensionMetadataDao ?? ExtensionMetadataDao::getInstance($queryContext->getAccountId());

		return $this->retrieveIdToDtoCollection($this->extensionsToLoad, $extensionMetadataDao);
	}

	/**
	 * @param array $ids
	 * @param RetrievableMetadataDao $dao
	 * @return array
	 * @throws MetadataDtoException
	 */
	private function retrieveIdToDtoCollection(array $ids, RetrievableMetadataDao $dao)
	{
		$metadataDtoIds = [];
		foreach(array_keys($ids) as $id) {
			if (\SalesforceIdManager::validateId($id)) {
				$metadataDtoIds[$id] = new \MetadataDtoIdentifier($id);
			}
		}
		if (count($metadataDtoIds) == 0) {
			return [];
		}

		$dtos = $dao->retrieveByIdentifiers($metadataDtoIds);
		$idToDtoCollection = [];
		foreach ($dtos as $dto) {
			$idToDtoCollection[$dto->getIdentifier()->getLongFormId()] = $dto;
		}

		return $idToDtoCollection;
	}

}

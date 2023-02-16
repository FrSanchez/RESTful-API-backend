<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use apiTools;
use Api\Objects\Query\QueryContext;
use Doctrine_Core;
use Doctrine_Query;
use Exception;
use piFormHandlerTable;
use piFormTable;
use piLandingPageTable;
use piMultivariateTestVariationTable;
use piProspectConversion;
use generalTools;
use piVideoTable;

class ProspectConversionBulkDataProcessor extends AbstractProspectExtendedFieldBulkDataProcessor
{
	protected array $supportedFields = [
		"convertedAt", "converted_at",
		"convertedFromObjectType", "converted_from_object_type",
		"convertedFromObjectName", "converted_from_object_name",
	];
	private array $fieldIndices;
	/** @var array[] $objectNamesByTypeAndId local cache of asset names indexed by type and object id.  The lifetime of
	 * this cache is scoped to the lifetime of the bulk data processor instance.  New instance is created for each
	 * "batched query" of each exported chunk and goes out of scope upon ExportManager returning from call to
	 * BulkDataManager::processRecordsForBulkData().  This limited scope ensures that cache does not grow unbounded.
	 */
	private array $objectNamesByTypeAndId = [
		generalTools::FORM => [],
		generalTools::LANDING_PAGE => [],
		generalTools::FORM_HANDLER => [],
		generalTools::MULTIVARIATE_TEST_VARIATION => [],
		generalTools::VIDEO => []
	];

	/**
	 * ProspectConversionBulkDataProcessor constructor.
	 */
	public function __construct()
	{
		$this->fieldIndices = array_flip($this->supportedFields);
	}

	/**
	 * @inheritDoc
	 */
	public function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica): void
	{
		$select = "pc.prospect_id";
		$prefix = ", ";
		$isObjectTypeSelected = false;

		if ($this->isFieldSelected("convertedAt") || $this->isFieldSelected("converted_at")) {
			$select .= $prefix . "pc.created_at";
		}
		if ($this->isFieldSelected("convertedFromObjectType") || $this->isFieldSelected("converted_from_object_type") ||
			$this->isFieldSelected("convertedFromObjectName") || $this->isFieldSelected("converted_from_object_name")) {
			$select .= $prefix . "pc.object_type";
			$isObjectTypeSelected = true;
		}
		$accountId = $queryContext->getAccountId();
		if ($this->isFieldSelected("convertedFromObjectName") || $this->isFieldSelected("converted_from_object_name")) {
			$select .= $prefix . "pc.object_id";
			$this->doFetchUsingMultipleQueries($select, $accountId, $prospectIdsToLoad);
		} else {
			$query = Doctrine_Query::create();
			$query->select($select)->from('piProspectConversion pc')
				->where('pc.account_id = ?', [$accountId])
				->andWhereIn('pc.prospect_id', array_keys($prospectIdsToLoad));
			if($allowReadReplica) {
				$query->readReplicaSafe();
			}
			$queryResults = $query->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_OBJECT_ARRAY);
			$idxDate = $this->fieldIndices["convertedAt"];
			$idxType = $this->fieldIndices["convertedFromObjectType"];

			foreach ($queryResults as $queryResult) {
				$fieldValues = [];
				$fieldValues[$idxDate] = $queryResult['created_at'] ?? null;
				if ($isObjectTypeSelected) {
					$fieldValues[$idxType] = $this->getObjectTypeString($queryResult['object_type'] ?? null);
				}
				$this->fetchedData[$queryResult['prospect_id']] = $fieldValues;
			}
		}
	}

	protected function getDbValue(int $recordId, $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$arrayOfDbValues = $this->fetchedData[$recordId] ?? [];
		if (count($arrayOfDbValues) == 0) {
			return null;
		}

		switch ($selection->getName()) {
			case 'converted_at':
			case 'convertedAt':
				return $arrayOfDbValues[$this->fieldIndices['convertedAt']] ?? null;
			case 'converted_from_object_type':
			case 'convertedFromObjectType':
				return $arrayOfDbValues[$this->fieldIndices['convertedFromObjectType']] ?? null;
			case 'converted_from_object_name':
			case 'convertedFromObjectName':
				return $arrayOfDbValues[$this->fieldIndices['convertedFromObjectName']] ?? null;
			default:
				return null;
		}
	}

	/**
	 * @param string $select
	 * @param int $accountId
	 * @param array $prospectIdsToLoad
	 * @throws Exception
	 */
	private function doFetchUsingMultipleQueries(string $select, int $accountId, array $prospectIdsToLoad): void
	{
		$idxDate = $this->fieldIndices["convertedAt"];
		$idxType = $this->fieldIndices["convertedFromObjectType"];
		$idxName = $this->fieldIndices["convertedFromObjectName"];
		$prospectIdsByObjTypeAndId = [
			generalTools::FORM => [],
			generalTools::LANDING_PAGE => [],
			generalTools::FORM_HANDLER => [],
			generalTools::MULTIVARIATE_TEST_VARIATION => [],
			generalTools::VIDEO => []
		];
		$queryResults = Doctrine_Query::create()
			->select($select)
			->from('piProspectConversion pc')
			->where('pc.account_id = ?', [$accountId])
			->andWhereIn('pc.prospect_id', array_keys($prospectIdsToLoad))
			->readReplicaSafe()
			->executeAndFree([], Doctrine_Core::HYDRATE_PARDOT_FIXED_ARRAY);

		// fill convertedAt and convertedFromObjectType data for prospect
		foreach ($queryResults as $queryResult) {
			$fieldValues = [];
			$fieldValues[$idxDate] = $queryResult['created_at'] ?? null;
			$type = $queryResult['object_type'] ?? null;
			$fieldValues[$idxType] = $this->getObjectTypeString($type);
			$objId = $queryResult['object_id'] ?? null;
			$fieldValues["obj_id"] = $objId;
			$prospectId = $queryResult['prospect_id'];
			if (array_key_exists($type, $prospectIdsByObjTypeAndId)) {
				if (array_key_exists($objId, $this->objectNamesByTypeAndId[$type])) {
					// cache hit - fill convertedFromObjectName from cache
					$fieldValues[$idxName] = $this->objectNamesByTypeAndId[$type][$objId];
				} else {
					// keep track of prospects that need object name data to be fetched
					if (!array_key_exists($objId, $prospectIdsByObjTypeAndId[$type])) {
						$prospectIdsByObjTypeAndId[$type][$objId] = [];
					}
					$prospectIdsByObjTypeAndId[$type][$objId][] = $prospectId;
				}
			}
			$this->fetchedData[$prospectId] = $fieldValues;
		}
		// fetch form names for prospects converted from form that were not found in cache
		if (count($prospectIdsByObjTypeAndId[generalTools::FORM]) > 0) {
			$query = piFormTable::getInstance()->createQuery('INDEXBY id');
			$this->fillObjectNameForProspects(
				$accountId,
				generalTools::FORM,
				$query,
				$prospectIdsByObjTypeAndId,
				$idxName
			);
		}
		// fetch landing page names for prospects converted from landing pages that were not found in cache
		if (count($prospectIdsByObjTypeAndId[generalTools::LANDING_PAGE]) > 0) {
			$query = piLandingPageTable::getInstance()->createQuery('INDEXBY id');
			$this->fillObjectNameForProspects(
				$accountId,
				generalTools::LANDING_PAGE,
				$query,
				$prospectIdsByObjTypeAndId,
				$idxName
			);
		}
		// fetch form handler names for prospects converted from form handlers that were not found in cache
		if (count($prospectIdsByObjTypeAndId[generalTools::FORM_HANDLER]) > 0) {
			$query = piFormHandlerTable::getInstance()->createQuery('INDEXBY id');
			$this->fillObjectNameForProspects(
				$accountId,
				generalTools::FORM_HANDLER,
				$query,
				$prospectIdsByObjTypeAndId,
				$idxName
			);
		}
		// fetch multivariate test names for prospects converted from assets that were not found in cache
		if (count($prospectIdsByObjTypeAndId[generalTools::MULTIVARIATE_TEST_VARIATION]) > 0) {
			$queryResults = piMultivariateTestVariationTable::getInstance()->createQuery('mtv')
				->select('mtv.id, mt.name AS obj_name')
				->innerJoin('mtv.piMultivariateTest mt')
				->where('mtv.account_id = ?', $accountId)
				->andWhereIn('mtv.id', array_keys($prospectIdsByObjTypeAndId[generalTools::MULTIVARIATE_TEST_VARIATION]))
				->readReplicaSafe()
				->executeAndFree();
			foreach ($queryResults as $queryResult) {
				$objId = $queryResult["id"];
				$objectNamesByTypeAndId[generalTools::MULTIVARIATE_TEST_VARIATION][$objId] = $queryResult['obj_name']; // add to cache
				foreach ($prospectIdsByObjTypeAndId[generalTools::MULTIVARIATE_TEST_VARIATION][$objId] as $prospectId) {
					$this->fetchedData[$prospectId][$idxName] = $queryResult['obj_name']; // fill name data
				}
			}
		}
		// fetch video asset names for prospects converted from videos that were not found in cache
		if (count($prospectIdsByObjTypeAndId[generalTools::VIDEO]) > 0) {
			$query = piVideoTable::getInstance()->createQuery('INDEXBY id');
			$this->fillObjectNameForProspects(
				$accountId,
				generalTools::VIDEO,
				$query,
				$prospectIdsByObjTypeAndId,
				$idxName
			);
		}
	}

	/**
	 * @param int $accountId
	 * @param int $type
	 * @param Doctrine_Query $query
	 * @param array $prospectIdsByObjTypeAndId
	 * @param int $idxName
	 * @throws Exception
	 */
	private function fillObjectNameForProspects(int $accountId, $type, Doctrine_Query $query, array $prospectIdsByObjTypeAndId, $idxName): void
	{
		$queryResults = $query->select('name')
			->where('account_id = ?', $accountId)
			->andWhereIn('id', array_keys($prospectIdsByObjTypeAndId[$type]))
			->readReplicaSafe()
			->executeAndFree();
		foreach ($queryResults as $objId => $queryResult) {
			$objectNamesByTypeAndId[$type][$objId] = $queryResult['name']; // add to cache
			foreach ($prospectIdsByObjTypeAndId[$type][$objId] as $prospectId) {
				$this->fetchedData[$prospectId][$idxName] = $queryResult['name']; // fill name data
			}
		}
	}

	private function getObjectTypeString($typeNo): ?string
	{
		if ($typeNo === null) {
			return null;
		}
		// Original code for this used piProspectConversion::getLabelForConversionObjectType() to retrieve the string.
		// This was changed because the label is not appropriate for API use.  The API object type strings are
		// more appropriate.  We check to see if there is a label before returning the more appropriate string in
		// order to limit return values to those corresponding to the labels that will be synced to leads and contacts.
		return is_null(piProspectConversion::getLabelForConversionObjectType($typeNo, false)) ? null : apiTools::getObjectNameFromId($typeNo);
	}
}

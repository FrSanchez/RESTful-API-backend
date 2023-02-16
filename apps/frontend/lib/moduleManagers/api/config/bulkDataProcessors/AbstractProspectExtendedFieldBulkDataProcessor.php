<?php
namespace Api\Config\BulkDataProcessors;

use AccountSettingsConstants;
use AccountSettingsManager;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use Exception;
use GraphiteClient;
use RuntimeException;
use Doctrine_Query_Exception;
use ShardManager;

abstract class AbstractProspectExtendedFieldBulkDataProcessor implements BulkDataProcessor
{
	const FIELD_ID = "id";
	const MAX_PROSPECTS_PER_BULK_QUERY_DEFAULT = 4000; // 0 means do not reduce into smaller batches

	/**
	 * @var array $prospectIdsToLoad associative array indexed by prospect id. Ids that require loading additional data
	 * during the fetch cycle should be added to this array.  Derived classes may override behavior for populating this
	 * array.  This array is expected to be populated by addRecordToLoadIfNeedsLoading calls prior to doFetchData call
	 */
	private array $prospectIdsToLoad = [];


	/**
	 * @var array $loadedProspectIds associative array indexed by prospect id.  Ids that were previously added to
	 * $prospectIdsToLoad but have since been processed by doFetchData are added to this array to keep track of
	 * cumulative set of prospect ids for which data has been loaded
	 */
	private array $loadedProspectIds = [];

	/**
	 * @var array $fetchedData array of associative arrays where top-level array is indexed by prospectId and secondary
	 * array contains key/value pairs where key is the selected field name in v5 camelcase format.  Derived classes that
	 * override default implementation of getDbValue are not required to use this data structure.  This array may be
	 * sparsely populated when compared to $loadedProspectIds depending on which prospects had the requested data.
	 */
	protected array $fetchedData = [];

	/**
	 * @var array $supportedFields array of field names in v5 API camelcase format that are supported by the bulk data
	 * processor concrete class.  This array must be populated when the object instance is constructed.
	 */
	protected array $supportedFields = [];

	/**
	 * @var array $selectedFields associative array indexed by field name in v5 API camelcase format. Contains full set
	 * of selected fields, including but not limited to set of selected fields that are supported by the concrete class.
	 * The array is populated prior to calling doFetchData on the concrete class
	 * @see AbstractProspectExtendedFieldBulkDataProcessor::isFieldSelected()
	 */
	private array $selectedFields = [];

	/**
	 * @inheritDoc
	 *
	 * NOTE: modifyPrimaryQueryBuilder stage of bulk data load is performed on separate instances of bulk data processor
	 * objects than bulk data processor instances used to perform check/fetch/modifyRecord cycle so be aware that there
	 * is no shared state between these separate instances
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void {
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
		$this->addFieldSelectionToPrimaryQuery($queryBuilderNode);
	}

	/**
	 * @inheritDoc
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (!($selection instanceof FieldDefinition) ||
			$objectDefinition->getType() !== 'Prospect') {
			throw new RuntimeException(
				"Unexpected selection or object definition.  Expected FieldDefinition for Prospect object"
			);
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}
		$fieldName = $selection->getName();
		$this->selectedFields[$fieldName] = true;

		// If we've already loaded this record previously, then we don't need to load it again
		if (!array_key_exists($recordId, $this->loadedProspectIds)) {
			if (!$this->addRecordToLoadIfNeedsLoading($recordId, $doctrineRecord)) {
				$this->loadedProspectIds[$recordId] = true;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica) : void
	{
		$numProspectsToLoad = count($this->prospectIdsToLoad);
		if ($numProspectsToLoad == 0) {
			// nothing to fetch
			return;
		}
		$batchSize = $this->getBatchSize($queryContext->getAccountId(), $numProspectsToLoad);

		// break the fetch requests into smaller batches
		$offset = 0;
		try {
			do {
				$batch = array_slice($this->prospectIdsToLoad, $offset, $batchSize, true);
				$this->doFetchData($queryContext, $batch, $allowReadReplica);
				$offset += $batchSize;
			} while ($offset < $numProspectsToLoad);
		} catch (Exception $exception) {
			$shardId = ShardManager::getInstance()->getCurrentConnectedShardId();
			$className = $this->getBulkQueryBatchSizeLimitOverrideKey();
			$this->incrementMetric('api.objects.query.' . strtolower($className) . '.error.shard' . $shardId);
			throw $exception;
		}
		$this->loadedProspectIds += $this->prospectIdsToLoad;
		// reset the state of the records to load
		$this->prospectIdsToLoad = [];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyRecord (
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion
	): bool {
		if (is_null($doctrineRecord)) {
			return false;
		}
		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return false;
		}
		// request more data to be fetched if doFetchData was not previously requested for this prospect record
		if (!array_key_exists($recordId, $this->loadedProspectIds)) {
			return true;
		}
		$dbValue = $this->getDbValue($recordId, $selection, $doctrineRecord);
		$dbArray[$selection->getName()] = $dbValue;
		return false;
	}

	/**
	 * @param string $fieldName field name in v5 API camelcase format
	 * @return bool True if the specified field is supported by the bulk data processor concrete class
	 */
	protected function isFieldSupported(string $fieldName) : bool
	{
		return in_array($fieldName, $this->supportedFields);
	}

	/**
	 * @param string $fieldName field name in v5 API camelcase format
	 * @return bool True if the specified field is a supported field that has been selected for this fetch cycle
	 */
	protected function isFieldSelected(string $fieldName) : bool
	{
		return array_key_exists($fieldName, $this->selectedFields) && $this->isFieldSupported($fieldName);
	}

	/**
	 * Derived classes that require additional fields besides FIELD_ID be added to the primary query must override
	 * this method to add the additional field selections.
	 *
	 * @param QueryBuilderNode $queryBuilderNode
	 */
	protected function addFieldSelectionToPrimaryQuery(QueryBuilderNode $queryBuilderNode) : void
	{
		// default implementation does not request additional field selections
	}

	/**
	 * Derived classes that require specialized logic to decide which records require loading additional data
	 * must override this method.  Default inherited behavior is to mark all records as requiring additional data
	 * to be loaded during the fetch cycle
	 *
	 * @param int $recordId
	 * @param ImmutableDoctrineRecord $doctrineRecord
	 * @param bool|string|int $value optional value to associated with the prospect id as bool, string, or integer
	 * @return bool True if the specified record was added to the $prospectsToBeLoaded array
	 */
	protected function addRecordToLoadIfNeedsLoading(int $recordId, ImmutableDoctrineRecord $doctrineRecord, $value = true) : bool
	{
		$this->prospectIdsToLoad[$recordId] = $value;
		return true;
	}

	/**
	 * @return int specifying built-in default batch size value for the class.  This may be overridden by child class
	 */
	protected function getDefaultBulkQueryBatchSizeLimit() : int
	{
		return self::MAX_PROSPECTS_PER_BULK_QUERY_DEFAULT;
	}

	/**
	 * @return string specifying the child class name that is used as key to lookup batch size override account setting
	 */
	protected function getBulkQueryBatchSizeLimitOverrideKey() : string
	{
		$components = explode('\\', get_class($this));
		return end($components);
	}

	/**
	 * Derived classes must implement this method to fetch data for records that need data to be loaded as determined
	 * by preceding addRecordToLoadIfNeedsLoading calls.  For derived classes that do not override the default
	 * modifyRecord method implementation, fetched data is expected to be added to the fetchedData member variable.
	 * Implementations of this method may use the isFieldSelected helper method to determine field selections.
	 *
	 * @param QueryContext $queryContext
	 * @param array $prospectIdsToLoad
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Query_Exception
	 * @throws Exception
	 */
	abstract protected function doFetchData(QueryContext $queryContext, array $prospectIdsToLoad, bool $allowReadReplica) : void;

	/**
	 * Derived classes that require specialized logic to extract or derive value from the $fetchedData must override
	 * this method.
	 *
	 * @param int $recordId
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord $doctrineRecord
	 * @return mixed db value to be populated for the specified record and field selection
	 */
	protected function getDbValue (int $recordId, FieldDefinition $selection, ImmutableDoctrineRecord $doctrineRecord)
	{
		$arrayOfDbValues = $this->fetchedData[$recordId] ?? [];
		return $arrayOfDbValues[$selection->getName()] ?? null;
	}

	/**
	 * @param int $accountId
	 * @param int $numProspectsToLoad
	 * @return int number of prospects to fetch data for per batch based on built-in default setting or override setting
	 */
	protected function getBatchSize(int $accountId, int $numProspectsToLoad) : int
	{
		$batchSize = $this->getDefaultBulkQueryBatchSizeLimit();
		$accountSettingsManager = AccountSettingsManager::getInstance($accountId);
		$batchSizeOverride = $accountSettingsManager->getValue(
			AccountSettingsConstants::SETTING_EXPORT_PROSPECT_EXTENDED_FIELDS_BULK_QUERY_BATCHSIZE_OVERRIDE,
			0
		);
		if (!empty($batchSizeOverride)) {
			if (is_string($batchSizeOverride)) {
				$settings = json_decode($batchSizeOverride, true);
				if (!is_null($settings)) {
					$key = $this->getBulkQueryBatchSizeLimitOverrideKey();
					if (array_key_exists($key, $settings)) {
						$batchSizeOverride = $settings[$key];
					}
				}
			}
			if ((is_numeric($batchSizeOverride) && $batchSizeOverride > 0)) {
				$batchSize = intval($batchSizeOverride);
			}
		}

		// disable batching if built-in default batch size specified by class is 0
		if ($batchSize == 0) {
			$batchSize = $numProspectsToLoad;
		}
		return $batchSize;
	}

	/**
	 * @param string $metricName
	 */
	protected function incrementMetric(string $metricName) : void
	{
		GraphiteClient::increment($metricName);
	}
}

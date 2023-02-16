<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use RuntimeException;
use TrackerDomainVanityUrlAssetTrait;
use ReflectionException;
use AccountSettingsManager;
use AccountSettingsConstants;
use piTrackerDomainTable;
use piAccountTable;
use Hostname;
use piTrackerDomain;
use Doctrine_Query_Exception;

class TrackerDomainBulkDataProcessorHelper
{
	/**
	 * Stores the information about the objects and the fields that are supported and also which fields need to be
	 * added to the primary query.
	 * @var array[][]
	 */
	const ALLOWED_OBJECT_FIELDS = [
		"File" => [
			"url" => [
				SystemColumnNames::ID,
				SystemColumnNames::S3_KEY,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			]
		],
		"CustomRedirect" => [
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
			"trackedUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			]
		],
		"Form" => [
			"embedCode" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			]
		],
		"DynamicContent" => [
			"embedCode" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
			"embedUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			]
		],
		"LandingPage" => [
			"url" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
		],
		"FormHandler" => [
			"embedCode" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
			"url" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
		],
	];

	/** @var array $trackerDomainIdsToRetrieve */
	private array $trackerDomainIdsToRetrieve;

	/** @var array $loadedRecordsForTrackerDomain */
	private array $loadedRecordsForTrackerDomain;

	/**
	 * Tracker Domains that are currently serving
	 * @var array $servingTrackerDomains
	 */
	private array $servingTrackerDomains;

	/**
	 * Use the default tracker domain for records when the Feature flg for FEATURE_MULTIPLE_CNAMES is not enabled
	 * @var bool $useDefaultTrackerDomainForAll
	 */
	private bool $useDefaultTrackerDomainForAll;

	/** @var string|null $defaultTrackerDomainUrl */
	private ?string $defaultTrackerDomainUrl;

	/** @var int $version */
	private int $version;

	/**
	 * TrackerDomainBulkDataProcessorHelper constructor.
	 * @param int $version
	 */
	public function __construct(int $version)
	{
		$this->trackerDomainIdsToRetrieve = [];
		$this->loadedRecordsForTrackerDomain = [];
		$this->servingTrackerDomains = [];
		$this->useDefaultTrackerDomainForAll = false;
		$this->defaultTrackerDomainUrl = null;
		$this->version = $version;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @return bool
	 */
	private function doesObjectFieldExistInAllowedList(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection
	) : bool {
		if (!array_key_exists($objectDefinition->getType(), self::ALLOWED_OBJECT_FIELDS)) {
			return false;
		}

		$allowedFields = self::ALLOWED_OBJECT_FIELDS[$objectDefinition->getType()];
		$fieldName = $selection->getName();

		if (!array_key_exists($fieldName, $allowedFields)) {
			return false;
		}

		return true;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param QueryBuilderNode $queryBuilderNode
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		QueryBuilderNode $queryBuilderNode
	) : void {
		if (!$this->doesObjectFieldExistInAllowedList($objectDefinition, $selection)) {
			return;
		}

		$fieldName = $selection->getName();
		$fieldsToAdd = self::ALLOWED_OBJECT_FIELDS[$objectDefinition->getType()][$fieldName];
		foreach ($fieldsToAdd as $fieldToAdd) {
			$queryBuilderNode->addSelection($fieldToAdd);
		}
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @throws ReflectionException
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		?ImmutableDoctrineRecord $doctrineRecord
	): void {
		if (!$this->doesObjectFieldExistInAllowedList($objectDefinition, $selection)) {
			return;
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($recordId)) {
			return;
		}

		$usesTrackerDomainTrait = $doctrineRecord
			->isDoctrineUsingTrait(TrackerDomainVanityUrlAssetTrait::class);
		if (!$usesTrackerDomainTrait) {
			throw new RuntimeException(
				"The doctrine record does not have the TrackerDomainVanityUrlAssetTrait."
			);
		}

		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);
		if (!is_null($trackerDomainId) && !$this->containsLoadedRecordForTrackerDomain($trackerDomainId)) {
			$this->trackerDomainIdsToRetrieve[] = $trackerDomainId;
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext): void
	{
		if (is_null($this->defaultTrackerDomainUrl)) {
			$this->useDefaultTrackerDomainForAll = !AccountSettingsManager::getInstance($queryContext->getAccountId())
				->isFlagEnabled(AccountSettingsConstants::FEATURE_MULTIPLE_CNAMES);

			$primaryTrackerDomain = piTrackerDomainTable::getInstance()
				->getPrimary($queryContext->getAccountId());
			$accountTrackerDomainString = piAccountTable::getInstance()
				->getTrackerDomain($queryContext->getAccountId());
			$this->defaultTrackerDomainUrl = $this->getTrackerDomainUrl(
				$primaryTrackerDomain,
				$accountTrackerDomainString,
				$queryContext->getAccountId()
			);

			if (!empty($primaryTrackerDomain) ) {
				$this->servingTrackerDomains[] = $primaryTrackerDomain->id;
				$this->trackerDomainIdsToRetrieve[] = $primaryTrackerDomain->id;
			}
		}

		if (empty($this->trackerDomainIdsToRetrieve)) {
			return;
		}

		if (!$this->useDefaultTrackerDomainForAll) {
			$allTrackerDomains = piTrackerDomainTable::getInstance()->retrieveByMultipleIds(
				$queryContext->getAccountId(),
				$this->trackerDomainIdsToRetrieve
			);

			/** @var piTrackerDomain $trackerDomain */
			foreach ($allTrackerDomains as $trackerDomain) {
				if ($trackerDomain->is_primary || $trackerDomain->isValidated()) {
					$this->loadedRecordsForTrackerDomain[$trackerDomain->id] = $trackerDomain->getHostname(true);
				} else {
					$this->loadedRecordsForTrackerDomain[$trackerDomain->id] = $this->defaultTrackerDomainUrl;
				}

				if ($trackerDomain->is_primary || $trackerDomain->isServing()) {
					$this->servingTrackerDomains[] = $trackerDomain->id;
				}
			}
		} else {
			foreach ($this->trackerDomainIdsToRetrieve as $trackerDomainId) {
				$this->loadedRecordsForTrackerDomain[$trackerDomainId] = $this->defaultTrackerDomainUrl;
			}
		}

		$this->trackerDomainIdsToRetrieve = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		if (is_null($doctrineRecord)) {
			return false;
		}

		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		if (is_null($currentRecordId)) {
			return false;
		}

		$fieldName = $selection->getName();
		if (($objectDefinition->getType() === "FormHandler" || $objectDefinition->getType() === "LandingPage" || $objectDefinition->getType() === "File") && $fieldName === "url") {
			return $this->handleUrlFieldModification(
				$objectDefinition,
				$currentRecordId,
				$fieldName,
				$dbArray,
				$doctrineRecord,
				$trackerBulkDataProcessorHelper
			);
		}
		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @return bool
	 */
	public function shouldModifyRecord(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection
	): bool {
		$fieldName = $selection->getName();
		return (($objectDefinition->getType() === "FormHandler" || $objectDefinition->getType() === "LandingPage" || $objectDefinition->getType() === "File") && $fieldName === "url");
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $currentRecordId
	 * @param string $fieldName
	 * @param array $dbArray
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	private function handleUrlFieldModification(
		ObjectDefinition $objectDefinition,
		int $currentRecordId,
		string $fieldName,
		array &$dbArray,
		?ImmutableDoctrineRecord $doctrineRecord,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);

		if ($this->needAdditionalInformationForLongTrackerUrl(
			$objectDefinition,
			$currentRecordId,
			$trackerDomainId,
			$trackerBulkDataProcessorHelper
		)) {
			return true;
		}

		$tracker = $trackerBulkDataProcessorHelper
			->getLoadedValueForTracker($objectDefinition, $currentRecordId);
		$domain = $this->getTrackerDomain($trackerDomainId);
		$url = $doctrineRecord->getLongUrlForTrackerDomain($domain, $tracker);
		$dbArray[$fieldName] = $url;
		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $currentRecordId
	 * @param int|null $trackerDomainId
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	public function needAdditionalInformationForLongTrackerUrl(
		ObjectDefinition $objectDefinition,
		int $currentRecordId,
		?int $trackerDomainId,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		if (!$trackerBulkDataProcessorHelper->containsLoadedRecordForTracker($objectDefinition, $currentRecordId)) {
			return true;
		}

		if (!is_null($trackerDomainId) &&
			!$this->useDefaultTrackerDomainForAll &&
			!$this->containsLoadedRecordForTrackerDomain($trackerDomainId)
		) {
			return true;
		}

		return false;
	}

	/**
	 * @param piTrackerDomain|false $primaryTrackerDomain
	 * @param string $accountTrackerDomainString
	 * @param int $accountId
	 * @return string
	 */
	private function getTrackerDomainUrl(
		$primaryTrackerDomain,
		string $accountTrackerDomainString,
		int $accountId
	): string {
		if (empty($primaryTrackerDomain)) {
			return $accountTrackerDomainString ?: 'http://' . Hostname::getTrackerHostname($accountId);
		}

		$scheme = $primaryTrackerDomain->isHttpsEnabled() ? 'https' : 'http';
		return "{$scheme}://{$primaryTrackerDomain->domain}";
	}

	/**
	 * @param int|null $trackerDomainId
	 * @return string
	 */
	public function getTrackerDomain(?int $trackerDomainId): string
	{
		return $this->useDefaultTrackerDomainForAll ? $this->defaultTrackerDomainUrl :
			(is_null($trackerDomainId) ?
				$this->defaultTrackerDomainUrl :
				$this->getLoadedValueForTrackerDomain($trackerDomainId));
	}

	/**
	 * @param int $trackerDomainId
	 * @return bool
	 */
	public function containsLoadedRecordForTrackerDomain(int $trackerDomainId): bool
	{
		return array_key_exists($trackerDomainId, $this->loadedRecordsForTrackerDomain);
	}

	/**
	 * @param int $trackerDomainId
	 * @return mixed|null
	 */
	public function getLoadedValueForTrackerDomain(int $trackerDomainId)
	{
		return $this->loadedRecordsForTrackerDomain[$trackerDomainId];
	}

	/**
	 * @param int $trackerDomainId
	 * @return bool
	 */
	public function isTrackerDomainServing(int $trackerDomainId): bool
	{
		return in_array($trackerDomainId, $this->servingTrackerDomains);
	}

	/**
	 * @return bool
	 */
	public function canUseDefaultTrackerDomain(): bool
	{
		return $this->useDefaultTrackerDomainForAll;
	}

	/**
	 * @return string
	 */
	public function getDefaultTrackerDomainUrl(): string
	{
		return $this->defaultTrackerDomainUrl;
	}
}

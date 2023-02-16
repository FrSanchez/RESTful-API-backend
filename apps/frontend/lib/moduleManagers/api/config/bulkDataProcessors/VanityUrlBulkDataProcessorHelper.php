<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemColumnNames;
use piGlobalAccountTable;
use piVanityUrlTable;
use Doctrine_Query_Exception;
use piVanityUrl;
use piForm;
use piFormHandler;

class VanityUrlBulkDataProcessorHelper
{
	/**
	 * Stores the information about the objects and the fields that are supported and also which fields need to be
	 * added to the primary query.
	 * @var array[][]
	 */
	const ALLOWED_OBJECT_FIELDS = [
		"File" => [
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
				SystemColumnNames::S3_KEY,
			],
			"vanityUrlPath" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
				SystemColumnNames::S3_KEY,
			],
		],
		"CustomRedirect" => [
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
			"vanityUrlPath" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
			"trackedUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
		],
		"Form" => [
			"embedCode" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
		],
		"FormHandler" => [
			"embedCode" => [
				SystemColumnNames::ID,
				SystemColumnNames::ACCOUNT_ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
			],
		],
		"LandingPage" => [
			"vanityUrl" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
			"vanityUrlPath" => [
				SystemColumnNames::ID,
				SystemColumnNames::TRACKER_DOMAIN_ID,
				SystemColumnNames::VANITY_URL_ID,
			],
		],
	];

	/** @var array $vanityUrlsToRetrieve */
	private array $vanityUrlsToRetrieve;

	/** @var array $loadedVanityUrls */
	private array $loadedVanityUrls;

	/** @var bool $canAccountUseVanityUrl */
	private bool $canAccountUseVanityUrl;

	/** @var int $version */
	private int $version;

	/**
	 * VanityUrlBulkDataProcessorHelper constructor.
	 * @param int $version
	 */
	public function __construct(int $version)
	{
		$this->vanityUrlsToRetrieve = [];
		$this->loadedVanityUrls = [];
		$this->canAccountUseVanityUrl = true;
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
	): void {
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

		$vanityUrlId = $doctrineRecord->get(SystemColumnNames::VANITY_URL_ID);
		if (!is_null($vanityUrlId) && !$this->containsLoadedRecordForVanityUrl($vanityUrlId)) {
			$this->vanityUrlsToRetrieve[] = $vanityUrlId;
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext): void
	{
		$this->canAccountUseVanityUrl = piGlobalAccountTable::getInstance()
			->canAccountUseVanityUrls($queryContext->getAccountId());

		if (empty($this->vanityUrlsToRetrieve)) {
			return;
		}

		$vanityUrls = piVanityUrlTable::getInstance()
			->retrieveByMultipleIds($this->vanityUrlsToRetrieve, $queryContext->getAccountId());
		foreach ($vanityUrls as $vanityUrl) {
			$this->loadedVanityUrls[$vanityUrl->id] = $vanityUrl;
		}

		$this->vanityUrlsToRetrieve = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @param TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		FieldDefinition $selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion,
		TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		if (is_null($doctrineRecord)) {
			return false;
		}

		$fieldName = $selection->getName();
		if (($objectDefinition->getType() === "LandingPage" || $objectDefinition->getType() === "File" || $objectDefinition->getType() === "CustomRedirect") &&
			$fieldName === "vanityUrlPath") {
			return $this->handleVanityUrlPathFieldModification(
				$doctrineRecord,
				$dbArray,
				$fieldName
			);
		}

		if (($objectDefinition->getType() === "LandingPage" || $objectDefinition->getType() === "File" || $objectDefinition->getType() === "CustomRedirect") &&
			$fieldName === "vanityUrl") {
			return $this->handleVanityUrlFieldModification(
				$objectDefinition,
				$doctrineRecord,
				$dbArray,
				$fieldName,
				$trackerDomainBulkDataProcessorHelper,
				$trackerBulkDataProcessorHelper
			);
		}

		if ($objectDefinition->getType() === "CustomRedirect" && $fieldName === "trackedUrl") {
			return $this->handleCustomFieldTrackedUrlFieldModification(
				$objectDefinition,
				$doctrineRecord,
				$dbArray,
				$fieldName,
				$trackerDomainBulkDataProcessorHelper,
				$trackerBulkDataProcessorHelper
			);
		}

		if (($objectDefinition->getType() === "Form" || $objectDefinition->getType() === "FormHandler") && $fieldName === "embedCode") {
			return $this->handleFormOrFormHandlerEmbedCodeFieldModification(
				$objectDefinition,
				$doctrineRecord,
				$dbArray,
				$fieldName,
				$trackerDomainBulkDataProcessorHelper,
				$trackerBulkDataProcessorHelper
			);
		}

		return false;
	}

	/**
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param string $fieldName
	 * @return bool
	 */
	private function handleVanityUrlPathFieldModification(
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		string $fieldName
	): bool {
		$vanityUrlId = $doctrineRecord->get(SystemColumnNames::VANITY_URL_ID);
		if (is_null($vanityUrlId)) {
			$dbArray[$fieldName] = null;
			return false;
		}

		if (!$this->containsLoadedRecordForVanityUrl($vanityUrlId)) {
			return true;
		}

		/** @var piVanityUrl $vanityUrl */
		$vanityUrl = $this->getLoadedValueForVanityUrl($vanityUrlId);
		$dbArray[$fieldName] = $vanityUrl->url;
		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param string $fieldName
	 * @param TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	private function handleVanityUrlFieldModification(
		ObjectDefinition $objectDefinition,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		string $fieldName,
		TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		$vanityUrlId = $doctrineRecord->get(SystemColumnNames::VANITY_URL_ID);
		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);

		if (is_null($currentRecordId) || is_null($vanityUrlId)) {
			$dbArray[$fieldName] = null;
			return false;
		}

		return $this->getUrlFromTrackerAndTrackerDomain(
			$objectDefinition,
			$doctrineRecord,
			$dbArray,
			$fieldName,
			$trackerDomainBulkDataProcessorHelper,
			$trackerBulkDataProcessorHelper,
			$currentRecordId,
			$vanityUrlId,
			$trackerDomainId
		);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param string $fieldName
	 * @param TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	private function handleCustomFieldTrackedUrlFieldModification(
		ObjectDefinition $objectDefinition,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		string $fieldName,
		TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		$vanityUrlId = $doctrineRecord->get(SystemColumnNames::VANITY_URL_ID);
		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);

		if (is_null($currentRecordId) ) {
			$dbArray[$fieldName] = '';
			return false;
		}

		return $this->getUrlFromTrackerAndTrackerDomain(
			$objectDefinition,
			$doctrineRecord,
			$dbArray,
			$fieldName,
			$trackerDomainBulkDataProcessorHelper,
			$trackerBulkDataProcessorHelper,
			$currentRecordId,
			$vanityUrlId,
			$trackerDomainId
		);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param string $fieldName
	 * @param TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @return bool
	 */
	private function handleFormOrFormHandlerEmbedCodeFieldModification(
		ObjectDefinition $objectDefinition,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		string $fieldName,
		TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	): bool {
		$currentRecordId = $doctrineRecord->get(SystemColumnNames::ID);
		$vanityUrlId = $doctrineRecord->get(SystemColumnNames::VANITY_URL_ID);
		$trackerDomainId = $doctrineRecord->get(SystemColumnNames::TRACKER_DOMAIN_ID);

		if (is_null($currentRecordId)) {
			$dbArray[$fieldName] = '';
			return false;
		}

		$needMoreInformation = $this->getUrlFromTrackerAndTrackerDomain(
			$objectDefinition,
			$doctrineRecord,
			$dbArray,
			$fieldName,
			$trackerDomainBulkDataProcessorHelper,
			$trackerBulkDataProcessorHelper,
			$currentRecordId,
			$vanityUrlId,
			$trackerDomainId
		);

		if ($needMoreInformation) {
			return true;
		}

		if ($objectDefinition->getType() === 'Form') {
			$dbArray[$fieldName] = (new piForm())->generateEmbedCode($dbArray[$fieldName]);
		} else { /* FormHandler */
			$dbArray[$fieldName] = (new PiFormHandler())->getFormCode($dbArray[$fieldName]);
		}
		return false;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param string $fieldName
	 * @param TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper
	 * @param TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper
	 * @param int $currentRecordId
	 * @param int|null $vanityUrlId
	 * @param int|null $trackerDomainId
	 * @return bool
	 */
	private function getUrlFromTrackerAndTrackerDomain(
		ObjectDefinition $objectDefinition,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		string $fieldName,
		TrackerDomainBulkDataProcessorHelper $trackerDomainBulkDataProcessorHelper,
		TrackerBulkDataProcessorHelper $trackerBulkDataProcessorHelper,
		int $currentRecordId,
		?int $vanityUrlId,
		?int $trackerDomainId
	): bool {
		if (is_null($vanityUrlId) ||  is_null($trackerDomainId) || !$this->canAccountUseVanityUrl ||
			!$trackerDomainBulkDataProcessorHelper->isTrackerDomainServing($trackerDomainId)
		) {
			if ($trackerDomainBulkDataProcessorHelper->needAdditionalInformationForLongTrackerUrl(
				$objectDefinition,
				$currentRecordId,
				$trackerDomainId,
				$trackerBulkDataProcessorHelper
			)) {
				return true;
			}

			$tracker = $trackerBulkDataProcessorHelper
				->getLoadedValueForTracker($objectDefinition, $currentRecordId);
			$domain = $trackerDomainBulkDataProcessorHelper->getTrackerDomain($trackerDomainId);
			$url = $doctrineRecord->getLongUrlForTrackerDomain($domain, $tracker);
			$dbArray[$fieldName] = $url;
			return false;
		}

		if (!$this->containsLoadedRecordForVanityUrl($vanityUrlId) ||
			(!$trackerDomainBulkDataProcessorHelper->canUseDefaultTrackerDomain() &&
				!$trackerDomainBulkDataProcessorHelper->containsLoadedRecordForTrackerDomain($trackerDomainId))
		) {
			return true;
		}

		$domain = $trackerDomainBulkDataProcessorHelper->canUseDefaultTrackerDomain() ?
			$trackerDomainBulkDataProcessorHelper->getDefaultTrackerDomainUrl() :
			$trackerDomainBulkDataProcessorHelper->getLoadedValueForTrackerDomain($trackerDomainId);
		/** @var piVanityUrl $vanityUrl */
		$vanityUrl = $this->getLoadedValueForVanityUrl($vanityUrlId);
		$dbArray[$fieldName] = $vanityUrl->getFullUrl(null, false, $domain);
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
		return (($objectDefinition->getType() === "LandingPage" || $objectDefinition->getType() === "File" || $objectDefinition->getType() === "CustomRedirect") &&
				($fieldName === "vanityUrlPath" || $fieldName === "vanityUrl")) ||
			($objectDefinition->getType() === "CustomRedirect" && $fieldName === "trackedUrl") ||
			(($objectDefinition->getType() === "Form" || $objectDefinition->getType() === "FormHandler") && $fieldName === "embedCode");
	}

	/**
	 * @param int $vanityUrlId
	 * @return bool
	 */
	private function containsLoadedRecordForVanityUrl(int $vanityUrlId): bool
	{
		return array_key_exists($vanityUrlId, $this->loadedVanityUrls);
	}

	/**
	 * @param int $vanityUrlId
	 * @return mixed|null
	 */
	private function getLoadedValueForVanityUrl(int $vanityUrlId)
	{
		return $this->loadedVanityUrls[$vanityUrlId];
	}
}

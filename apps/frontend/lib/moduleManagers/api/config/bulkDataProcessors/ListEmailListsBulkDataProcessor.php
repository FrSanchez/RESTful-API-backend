<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Collections\ObjectCollectionSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\SystemFieldNames;
use piEmailListxTable;
use RuntimeException;

class ListEmailListsBulkDataProcessor implements BulkDataProcessor
{
	public const FIELD_RECIPIENT_LISTS = 'recipientLists';
	public const FIELD_SUPPRESSION_LISTS = 'suppressionLists';

	private array $listEmailIdMap = [];
	private array $loadedListEmailIds = [];
	private array $recipientListRepresentationsByEmailIdMap = [];
	private array $suppressionListRepresentationsByEmailIdMap = [];
	private bool $shouldLoadRecipientLists = false;
	private bool $shouldLoadSuppressionLists = false;

	/**
	 * @inheritDoc
	 */
	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemFieldNames::ID);
	}

	/**
	 * @inheritDoc
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		if (!$selection instanceof ObjectCollectionSelection) {
			throw new RuntimeException('Unexpected selection type specified');
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$listEmailId = $doctrineRecord->get(SystemFieldNames::ID);
		if (is_null($listEmailId)) {
			return;
		}

		if (!array_key_exists($listEmailId, $this->loadedListEmailIds)) {
			$this->listEmailIdMap[$listEmailId] = true;

			if ($selection->getCollectionName() === self::FIELD_RECIPIENT_LISTS) {
				$this->shouldLoadRecipientLists = true;
			}

			if ($selection->getCollectionName() === self::FIELD_SUPPRESSION_LISTS) {
				$this->shouldLoadSuppressionLists = true;
			}
		}

	}

	/**
	 * @inheritDoc
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (empty($this->listEmailIdMap)) {
			return;
		}

		// Retrieve the mapping of email to list
		$emailListxRecords = piEmailListxTable::getInstance()->getByEmailIdsAndIsSuppressed(
			$queryContext->getAccountId(),
			array_keys($this->listEmailIdMap),
			$this->getIsSuppressedQueryValues()
		);

		if (count($emailListxRecords) === 0) {
			$this->loadedListEmailIds += $this->listEmailIdMap;
			return;
		}

		// Retrieve the related Lists' Representations
		$listObjectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType(
			$queryContext->getVersion(),
			$queryContext->getAccountId(),
			'List'
		);
		// Get all the selection values we need for this object definition
		$recordSelections = array_values(BulkDataProcessorRelationshipHelper::getSelectionsForObjectDefinition(
			$selections,
			$objectDefinition,
			$listObjectDefinition
		));
		$listxIds = array_map(fn ($listx) => $listx['listx_id'], $emailListxRecords->toArray());
		$listRepresentationsByIdMap = BulkDataProcessorRelationshipHelper::getAssetDetails(
			$queryContext,
			$recordSelections,
			$listxIds,
			$listObjectDefinition
		);

		// Map each of the related list representations back to the email
		$this->mapListRepresentationsToEmailId($emailListxRecords, $listRepresentationsByIdMap);

		$this->loadedListEmailIds += $this->listEmailIdMap;
		// Reset the lists to fetch
		$this->listEmailIdMap = [];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (!$selection instanceof ObjectCollectionSelection) {
			throw new RuntimeException('Unexpected selection type specified');
		}

		if (is_null($doctrineRecord)) {
			return false;
		}

		$listEmailId = $doctrineRecord->get(SystemFieldNames::ID);
		if (is_null($listEmailId)) {
			return false;
		}

		// Request more data to be fetched if fetchData was not previously requested for this list email record.
		if (!array_key_exists($listEmailId, $this->loadedListEmailIds)) {
			return true;
		}

		$collectionName = $selection->getCollectionName();
		if ($collectionName === self::FIELD_RECIPIENT_LISTS) {
			$dbArray[$collectionName] = $this->recipientListRepresentationsByEmailIdMap[$listEmailId] ?? null;
		}

		if ($collectionName === self::FIELD_SUPPRESSION_LISTS) {
			$dbArray[$collectionName] = $this->suppressionListRepresentationsByEmailIdMap[$listEmailId] ?? null;
		}

		return false;
	}

	private function getIsSuppressedQueryValues(): array
	{
		$isSuppressedValues = [];

		if ($this->shouldLoadRecipientLists) {
			$isSuppressedValues[] = 0;
		}

		if ($this->shouldLoadSuppressionLists) {
			$isSuppressedValues[] = 1;
		}

		return $isSuppressedValues;
	}

	/**
	 * @param \SplFixedArray $emailListxRecords
	 * @param array $listRepresentationsByIdMap
	 */
	private function mapListRepresentationsToEmailId($emailListxRecords, array $listRepresentationsByIdMap): void
	{
		foreach ($emailListxRecords as $emailListxRecord) {
			$listxId = (int)$emailListxRecord['listx_id'];
			$emailId = (int)$emailListxRecord['email_id'];
			$isSuppressed = (int)$emailListxRecord['is_suppressed'];

			if (!isset($listRepresentationsByIdMap[$listxId])) {
				// For some reason, the email_listx referenced a list ID that wasn't found in listx so just skip it
				continue;
			}

			$listRepresentation = $listRepresentationsByIdMap[$listxId];
			if ($isSuppressed) {
				$this->suppressionListRepresentationsByEmailIdMap[$emailId][] = $listRepresentation;
			} else {
				$this->recipientListRepresentationsByEmailIdMap[$emailId][] = $listRepresentation;
			}
		}
	}
}

<?php
namespace Api\Objects\Query;

use Api\Exceptions\ApiException;
use Api\Framework\ClassInstantiator;
use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Collections\ObjectCollectionSelection;
use Api\Objects\Collections\RepresentationCollectionSelection;
use Api\Objects\Collections\RepresentationReferenceSelection;
use Api\Objects\Collections\ScalarCollectionSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\FieldDefinition;
use Api\Objects\FieldsParser;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldRepresentationArraySelection;
use Api\Objects\Query\Selections\FieldScalarArraySelection;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Representations\RepresentationPropertyDefinition;
use Doctrine_Collection;
use Doctrine_Exception;
use PardotLogger;
use ApiErrorLibrary;
use RuntimeException;

class BulkDataManager
{
	private ClassInstantiator $classInstantiator;

	public function __construct(ClassInstantiator $classInstantiator)
	{
		$this->classInstantiator = $classInstantiator;
	}

	/**
	 * @param QueryContext $queryContext
	 * @param Doctrine_Collection $doctrineCollection
	 * @param array $dbArrays
	 * @param array $selections
	 * @param ObjectDefinition $objectDefinition
	 * @param bool $allowReadReplica
	 * @return bool true if the specified selections included fields that required fetching data via bulk data processor
	 * @throws Doctrine_Exception
	 */
	public function processRecordsForBulkData(
		QueryContext $queryContext,
		Doctrine_Collection $doctrineCollection,
		array &$dbArrays,
		array $selections,
		ObjectDefinition $objectDefinition,
		bool $allowReadReplica
	): bool {
		/** @var BulkDataProcessor[] $bulkDataProcessorsById */
		$bulkDataProcessorsById = [];

		$dataToRetrieve = true;
		$dataToRetrieveLoopCount = 0;
		while ($dataToRetrieve) {
			if ($dataToRetrieveLoopCount >= $this->getBulkDataRetrievalLimit()) {
				$this->logDataRetrievalLimitError($objectDefinition, $selections, $queryContext);
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_GENERIC_INTERNAL_ERROR,
					"Limit for retrieving bulk data reached."
				);
			}

			// Walk the records to see if anything needs loaded
			$this->walkRecordsAndGetDataLoadRequests(
				$doctrineCollection,
				$dbArrays,
				$selections,
				$objectDefinition,
				$bulkDataProcessorsById
			);

			// For each of the bulk data processors, have them do their fetching
			foreach ($bulkDataProcessorsById as $bulkDataProcessorId => $bulkDataProcessor) {
				$bulkDataProcessor->fetchData($queryContext, $objectDefinition, $selections, $allowReadReplica);
			}

			// Add all the new data found to the representation arrays and check if more data is required
			$dataToRetrieve = $this->walkRecordsAndSetFieldsBasedOnData(
				$doctrineCollection,
				$dbArrays,
				$selections,
				$objectDefinition,
				$bulkDataProcessorsById,
				$queryContext
			);
			$dataToRetrieveLoopCount++;
		}

		$this->postProcessRepresentationArrays($dbArrays, $selections, $queryContext);
		return count($bulkDataProcessorsById) > 0;
	}

	/**
	 * @return int
	 */
	protected function getBulkDataRetrievalLimit(): int
	{
		return FieldsParser::MAXIMUM_RELATIONSHIP_DEPTH + 1;
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param QueryContext $queryContext
	 */
	private function logDataRetrievalLimitError(
		ObjectDefinition $objectDefinition,
		array $selections,
		QueryContext $queryContext
	): void {
		$selectionNames = $this->getSelectionNames($selections, $queryContext->getVersion());
		$logger = PardotLogger::getInstance();
		$logger->addTags([
			'object' => $objectDefinition->getType(),
			'accountId' => $queryContext->getAccountId(),
			'selections' => json_encode($selectionNames),
		]);
		$logger->error("Bulk Data processor reached iteration limit for {$objectDefinition->getType()}");
	}

	/**
	 * @param array $selections
	 * @param int $version
	 * @return array
	 */
	private function getSelectionNames(array $selections, int $version): array
	{
		$selectionNames = [];
		foreach ($selections as $selection) {
			if ($selection instanceof FieldDefinition) {
				$selectionNames[] = $selection->getName();
			} elseif ($selection instanceof RelationshipSelection) {
				$childSelectionNames = [];
				$this->getSelectionNames(
					array_merge(
						$selection->getFieldSelections(),
						$selection->getChildRelationshipSelections()
					),
					$version
				);

				foreach ($childSelectionNames as $childSelectionName) {
					$selectionNames[] = $selection->getRelationship()->getName() . "." . $childSelectionName;
				}
			}
		}

		return $selectionNames;
	}

	/**
	 * Walks the records array and selections graph to determine which BulkDataProviders are needed. If a
	 * BulkDataProvider is needed, the BulkDataProvider registers a new load within itself (to be loaded in a
	 * later step).
	 *
	 * @param Doctrine_Collection $doctrineCollection
	 * @param array $dbArrays
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 * @param ObjectDefinition $objectDefinition
	 * @param BulkDataProcessor[] $bulkDataProcessorsById
	 */
	private function walkRecordsAndGetDataLoadRequests(
		Doctrine_Collection $doctrineCollection,
		array $dbArrays,
		array $selections,
		ObjectDefinition $objectDefinition,
		array &$bulkDataProcessorsById
	): void {
		$recordIndex = 0;
		foreach ($doctrineCollection as $doctrineRecord) {
			$immutableDoctrineRecord = ImmutableDoctrineRecord::of($doctrineRecord);
			$dbArray = &$dbArrays[$recordIndex];
			$this->walkRecordAndGetDataLoadRequests($immutableDoctrineRecord, $dbArray, $selections, $objectDefinition, $bulkDataProcessorsById);
			$recordIndex++;
		}
	}

	/**
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param FieldDefinition[]|FieldSelection[]|RelationshipSelection[] $selections
	 * @param ObjectDefinition $objectDefinition
	 * @param BulkDataProcessor[] $bulkDataProcessorsById
	 */
	private function walkRecordAndGetDataLoadRequests(
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray,
		array $selections,
		ObjectDefinition $objectDefinition,
		array &$bulkDataProcessorsById
	): void	{
		foreach ($selections as $selection) {
			// Handle the Bulk Fields
			if (($selection instanceof FieldDefinition && $selection->isBulkField()) ||
				($selection instanceof FieldScalarSelection && $selection->isBulkField()) ||
				($selection instanceof FieldScalarArraySelection && $selection->isBulkField())) {
				if ($selection instanceof FieldDefinition) {
					$fieldDefinition = $selection;
				} else {
					$fieldDefinition = $selection->getFieldDefinition();
				}
				$providerName = $fieldDefinition->getBulkDataProcessorClass();

				if (!isset($bulkDataProcessorsById[$providerName])) {
					$bulkDataProcessorsById[$providerName] = $this->classInstantiator->instantiateFromId($providerName);
				}

				$bulkDataProcessorsById[$providerName]
					->checkAndAddRecordToLoadIfNeedsLoading($objectDefinition, $fieldDefinition, $doctrineRecord, $dbArray);
			}

			if ($selection instanceof FieldRepresentationArraySelection) {
				// Representation arrays must always be bulk processed

				$fieldDefinition = $selection->getFieldDefinition();
				$providerName = $fieldDefinition->getBulkDataProcessorClass();

				if (!isset($bulkDataProcessorsById[$providerName])) {
					$bulkDataProcessorsById[$providerName] = $this->classInstantiator->instantiateFromId($providerName);
				}

				$bulkDataProcessorsById[$providerName]
					->checkAndAddRecordToLoadIfNeedsLoading($objectDefinition, $selection, $doctrineRecord, $dbArray);
			}

			// Handle the bulk relationships
			if ($selection instanceof RelationshipSelection && $selection->getRelationship()->isBulkRelationship()) {
				$providerName = $selection->getRelationship()->getBulkDataProcessorClass();

				if (!isset($bulkDataProcessorsById[$providerName])) {
					$bulkDataProcessorsById[$providerName] = $this->classInstantiator->instantiateFromId($providerName);
				}

				$bulkDataProcessorsById[$providerName]
					->checkAndAddRecordToLoadIfNeedsLoading($objectDefinition, $selection, $doctrineRecord, $dbArray);
			}

			// Handle relationships
			if ($selection instanceof RelationshipSelection) {
				if (!isset($dbArray[$selection->getRelationship()->getName()])) {
					continue;
				}

				$childRepresentationArray = array($dbArray[$selection->getRelationship()->getName()]);
				$childRecord = ImmutableDoctrineRecord::of($this->getChildRecordFromRelationship($doctrineRecord, $selection));
				$this->walkRecordAndGetDataLoadRequests(
					$childRecord,
					$childRepresentationArray,
					array_merge(
						$selection->getFieldSelections(),
						$selection->getChildRelationshipSelections()
					),
					$selection->getReferencedObjectDefinition(),
					$bulkDataProcessorsById
				);
			}

			// Handle collections
			if ($selection instanceof CollectionSelection) {
				$providerName = $selection->getBulkDataProcessorClass();

				if (!isset($bulkDataProcessorsById[$providerName])) {
					$bulkDataProcessorsById[$providerName] = $this->classInstantiator->instantiateFromId($providerName);
				}

				$bulkDataProcessorsById[$providerName]
					->checkAndAddRecordToLoadIfNeedsLoading($objectDefinition, $selection, $doctrineRecord, $dbArray);
			}
		}
	}

	/**
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param RelationshipSelection $selection
	 * @return ImmutableDoctrineRecord|null
	 */
	private function getChildRecordFromRelationship(
		?ImmutableDoctrineRecord $doctrineRecord,
		RelationshipSelection $selection
	): ?ImmutableDoctrineRecord {
		if (is_null($doctrineRecord)) {
			return null;
		}

		if (!$doctrineRecord->hasReference($selection->getRelationship()->getDoctrineName())) {
			return null;
		}

		return $doctrineRecord->reference($selection->getRelationship()->getDoctrineName());
	}

	/**
	 * Updates all representations in the representation arrays with the information provided by the bulk data
	 * processors.
	 *
	 * @param Doctrine_Collection|array $doctrineCollection
	 * @param array $dbArrays
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 * @param ObjectDefinition $objectDefinition
	 * @param BulkDataProcessor[] $bulkDataProcessorsById
	 * @param QueryContext $queryContext
	 * @return bool True is there is more data needed
	 */
	private function walkRecordsAndSetFieldsBasedOnData(
		$doctrineCollection,
		array &$dbArrays,
		array $selections,
		ObjectDefinition $objectDefinition,
		array $bulkDataProcessorsById,
		QueryContext $queryContext
	): bool {
		$requireMoreData = false;
		$recordIndex = 0;
		foreach ($doctrineCollection as $doctrineRecord) {
			$dbArray = &$dbArrays[$recordIndex];
			$needsMoreData = $this->walkRecordAndSetFieldsBasedOnData(
				ImmutableDoctrineRecord::of($doctrineRecord),
				$dbArray,
				$selections,
				$objectDefinition,
				$bulkDataProcessorsById,
				$queryContext
			);

			if ($needsMoreData) {
				$requireMoreData = true;
			}
			$recordIndex++;
		}
		return $requireMoreData;
	}

	/**
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 * @param ObjectDefinition $objectDefinition
	 * @param BulkDataProcessor[] $bulkDataProcessorsById
	 * @param QueryContext $queryContext
	 * @return bool
	 */
	private function walkRecordAndSetFieldsBasedOnData(
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		array $selections,
		ObjectDefinition $objectDefinition,
		array $bulkDataProcessorsById,
		QueryContext $queryContext
	): bool {
		$requireMoreData = false;
		foreach ($selections as $selection) {
			// Handle the Bulk Fields
			if (($selection instanceof FieldDefinition && $selection->isBulkField()) ||
				($selection instanceof FieldScalarSelection && $selection->isBulkField()) ||
				($selection instanceof FieldScalarArraySelection && $selection->isBulkField())) {
				if ($selection instanceof FieldDefinition) {
					$fieldDefinition = $selection;
				} else {
					$fieldDefinition = $selection->getFieldDefinition();
				}
				$providerClass = $fieldDefinition->getBulkDataProcessorClass();

				if (isset($bulkDataProcessorsById[$providerClass])) {
					$bulkDataProcessor = $bulkDataProcessorsById[$providerClass];
					$needsMoreData = $bulkDataProcessor->modifyRecord(
						$objectDefinition,
						$fieldDefinition,
						$doctrineRecord,
						$dbArray,
						$queryContext->getVersion()
					);

					if ($needsMoreData) {
						$requireMoreData = true;
					}
				} else {
					$requireMoreData = true;
				}
			}

			// Handle array of representations
			if ($selection instanceof FieldRepresentationArraySelection) {
				$fieldDefinition = $selection->getFieldDefinition();
				$providerClass = $fieldDefinition->getBulkDataProcessorClass();

				if (isset($bulkDataProcessorsById[$providerClass])) {
					$bulkDataProcessor = $bulkDataProcessorsById[$providerClass];
					$needsMoreData = $bulkDataProcessor->modifyRecord(
						$objectDefinition,
						$selection,
						$doctrineRecord,
						$dbArray,
						$queryContext->getVersion()
					);

					if ($needsMoreData) {
						$requireMoreData = true;
					}
				} else {
					$requireMoreData = true;
				}
			}

			// Handle the Bulk Relationships
			if ($selection instanceof RelationshipSelection &&
				$selection->getRelationship()->isBulkRelationship()) {
				$providerClass = $selection->getRelationship()->getBulkDataProcessorClass();

				if (isset($bulkDataProcessorsById[$providerClass])) {
					$bulkDataProcessor = $bulkDataProcessorsById[$providerClass];
					$needsMoreData = $bulkDataProcessor->modifyRecord(
						$objectDefinition,
						$selection,
						$doctrineRecord,
						$dbArray,
						$queryContext->getVersion()
					);

					if ($needsMoreData) {
						$requireMoreData = true;
					}
				} else {
					$requireMoreData = true;
				}
			}

			if ($selection instanceof RelationshipSelection) {
				if (!isset($dbArray[$selection->getRelationship()->getName()])) {
					continue;
				}

				$childRepresentationArray = array(&$dbArray[$selection->getRelationship()->getName()]);
				$childRecord = $this->getChildRecordFromRelationship($doctrineRecord, $selection);
				$needsMoreData = $this->walkRecordsAndSetFieldsBasedOnData(
					array($childRecord),
					$childRepresentationArray,
					array_merge(
						$selection->getFieldSelections(),
						$selection->getChildRelationshipSelections()
					),
					$selection->getReferencedObjectDefinition(),
					$bulkDataProcessorsById,
					$queryContext
				);

				if ($needsMoreData) {
					$requireMoreData = true;
				}
			}

			// Handle collections
			if ($selection instanceof CollectionSelection) {
				$providerClass = $selection->getBulkDataProcessorClass();

				if (isset($bulkDataProcessorsById[$providerClass])) {
					$bulkDataProcessor = $bulkDataProcessorsById[$providerClass];
					$needsMoreData = $bulkDataProcessor->modifyRecord(
						$objectDefinition,
						$selection,
						$doctrineRecord,
						$dbArray,
						$queryContext->getVersion()
					);

					if ($needsMoreData) {
						$requireMoreData = true;
					}
				} else {
					$requireMoreData = true;
				}
			}
		}
		return $requireMoreData;
	}

	/**
	 * Removes all information that was not requested by the user for each representation in the representations arrays.
	 *
	 * @param array $representationArrays
	 * @param array $selections
	 * @param QueryContext $queryContext
	 */
	private function postProcessRepresentationArrays(
		array &$representationArrays,
		array $selections,
		QueryContext $queryContext
	): void {
		// Expect that the representationArrays is an indexed array so remove all non-numeric keys
		$keysToRemove = array_diff(array_keys($representationArrays), range(0, count($representationArrays) - 1));
		foreach ($keysToRemove as $keyToRemove) {
			unset($keysToRemove[$keyToRemove]);
		}

		foreach ($representationArrays as &$representationArray) {
			if (!is_array($representationArrays)) {
				throw new RuntimeException('Unexpected result. Expected array but got ' . get_class($representationArray) . '.');
			}

			$expectedValues = [];
			foreach ($selections as $selection) {
				if ($selection instanceof FieldDefinition) {
					$expectedValues[] = $selection->getName();
				} elseif ($selection instanceof FieldScalarSelection || $selection instanceof FieldScalarArraySelection) {
					$expectedValues[] = $selection->getName();
				} elseif ($selection instanceof FieldRepresentationArraySelection) {
					$expectedValues[] = $selection->getName();

					if (isset($representationArray[$selection->getFieldDefinition()->getName()])) {
						// The value in the representationArray should be an indexed array of other object representations.
						$collectionArray = &$representationArray[$selection->getFieldDefinition()->getName()];
						$this->postProcessRepresentationArrays(
							$collectionArray,
							array_merge(
								$selection->getRepresentationSelection()->getProperties(),
								$selection->getRepresentationSelection()->getRepresentationReferenceSelections(),
							),
							$queryContext
						);
					}

				} elseif ($selection instanceof RelationshipSelection) {
					$expectedValues[] = $selection->getRelationship()->getName();

					if (isset($representationArray[$selection->getRelationship()->getName()])) {
						$childRepresentationArray = array(&$representationArray[$selection->getRelationship()->getName()]);
						$this->postProcessRepresentationArrays(
							$childRepresentationArray,
							array_merge(
								$selection->getFieldSelections(),
								$selection->getChildRelationshipSelections()
							),
							$queryContext
						);
					}

				} elseif ($selection instanceof ScalarCollectionSelection) {
					$expectedValues[] = $selection->getCollectionName();

				} elseif ($selection instanceof ObjectCollectionSelection) {
					$expectedValues[] = $selection->getCollectionName();

					if (isset($representationArray[$selection->getCollectionName()])) {
						// The value in the representationArray should be an indexed array of other object representations.
						$collectionArray = &$representationArray[$selection->getCollectionName()];
						$this->postProcessRepresentationArrays(
							$collectionArray,
							array_merge(
								$selection->getFieldSelections(),
								$selection->getRelationshipSelections(),
								$selection->getCollectionSelections(),
							),
							$queryContext
						);
					}
				} elseif ($selection instanceof RepresentationCollectionSelection) {
					$expectedValues[] = $selection->getCollectionName();

					if (isset($representationArray[$selection->getCollectionName()])) {
						// The value in the representationArray should be an indexed array of other object representations.
						$collectionArray = &$representationArray[$selection->getCollectionName()];
						$this->postProcessRepresentationArrays(
							$collectionArray,
							array_merge(
								$selection->getProperties(),
								$selection->getRepresentationReferenceSelections(),
							),
							$queryContext
						);
					}
				} elseif ($selection instanceof RepresentationPropertyDefinition) {
					$expectedValues[] = $selection->getName();

				} elseif ($selection instanceof RepresentationReferenceSelection) {
					$expectedValues[] = $selection->getPropertyName();

					if (isset($representationArray[$selection->getPropertyName()])) {
						$childRepresentationArray = array(&$representationArray[$selection->getPropertyName()]);
						$this->postProcessRepresentationArrays(
							$childRepresentationArray,
							array_merge(
								$selection->getRepresentationSelection()->getProperties(),
								$selection->getRepresentationSelection()->getRepresentationReferenceSelections(),
							),
							$queryContext
						);
					}

				}
			}

			foreach ($representationArray as $key => $value) {
				if (!in_array($key, $expectedValues)) {
					unset($representationArray[$key]);
				}
			}
		}
	}
}

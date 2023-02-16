<?php
namespace Api\Objects\Query;

use Api\Objects\Collections\ObjectCollectionSelection;
use Api\Objects\Collections\ObjectCollectionSelectionBuilder;
use Api\Objects\Collections\RepresentationCollectionSelection;
use Api\Objects\Collections\RepresentationCollectionSelectionBuilder;
use Api\Objects\Collections\ScalarCollectionSelection;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\Selections\FieldRepresentationArraySelection;
use Api\Objects\Query\Selections\FieldRepresentationArraySelectionBuilder;
use Api\Objects\Query\Selections\FieldScalarArraySelection;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemFieldNames;
use Doctrine_Exception;
use Doctrine_Query_Exception;
use sfContext;

class BulkDataProcessorRelationshipHelper
{
	/**
	 * Returns a map of record id to record value for all ids that are requested for the object based on the selections.
	 *
	 * @param QueryContext $queryContext
	 * @param array $selections
	 * @param array $ids
	 * @param ObjectDefinition $objectDefinition
	 * @param bool $allowReadReplicaForBulkLoaders
	 * @return array
	 * @throws Doctrine_Exception
	 * @throws Doctrine_Query_Exception
	 */
	public static function getAssetDetails(
		QueryContext $queryContext,
		array $selections,
		array $ids,
		ObjectDefinition $objectDefinition,
		bool $allowReadReplicaForBulkLoaders = false
	): array {
		$recordIdToRecord = [];
		if (is_null($ids) || empty($ids)) {
			return $recordIdToRecord;
		}

		$query = ManyQuery::from($queryContext->getAccountId(), $objectDefinition, count($ids))
			->addSelections(...$selections)
			->addSelectAdditionalFields(SystemFieldNames::ID)
			->addWhereInCondition(SystemFieldNames::ID, $ids);

		/** @var ObjectQueryManager $queryManager */
		$queryManager = sfContext::getInstance()->getContainer()->get('api.objects.query.objectQueryManager');
		list($selections, $fieldsToRemove, $representations) = $queryManager->getDatabaseRepresentationForQuery($queryContext, $query, $allowReadReplicaForBulkLoaders);

		foreach ($representations as $representation) {
			if (!array_key_exists(SystemFieldNames::ID, $representation) || is_null($representation[SystemFieldNames::ID])) {
				continue;
			}

			$recordId = $representation[SystemFieldNames::ID];
			ObjectQueryManager::removeKeysRecursive($representation, $fieldsToRemove);
			$recordIdToRecord[$recordId] = $representation;
		}

		return $recordIdToRecord;
	}

	/**
	 * For each object definition, get all the data from the selections we need to retrieve for it.
	 *
	 * @param array $allSelections
	 * @param ObjectDefinition $currentObjectDefinition all selection for this object
	 * @param ObjectDefinition $selectionForObject Get the selections for this object
	 * @return array
	 */
	public static function getSelectionsForObjectDefinition(
		array $allSelections,
		ObjectDefinition $currentObjectDefinition,
		ObjectDefinition $selectionForObject
	): array {
		$selectionNameToSelection = [];
		self::collectSelectionsMatchingObjectDefinition($allSelections, $currentObjectDefinition, $selectionForObject, $selectionNameToSelection);

		// Convert any builders into immutable instances
		foreach ($selectionNameToSelection as $selectionName => $selection) {
			if ($selection instanceof ObjectCollectionSelectionBuilder) {
				$selectionNameToSelection[$selectionName] = $selection->build();
			} elseif ($selection instanceof RepresentationCollectionSelectionBuilder) {
				$selectionNameToSelection[$selectionName] = $selection->build();
			} elseif ($selection instanceof FieldRepresentationArraySelectionBuilder) {
				$selectionNameToSelection[$selectionName] = $selection->build();
			}
		}

		return $selectionNameToSelection;
	}

	private static function collectSelectionsMatchingObjectDefinition(
		array $allSelections,
		ObjectDefinition $currentObjectDefinition,
		ObjectDefinition $selectionForObject,
		array &$selectionNameToSelection = []
	): void {
		foreach ($allSelections as $selection) {
			if (($selection instanceof FieldDefinition || $selection instanceof FieldScalarSelection || $selection instanceof FieldScalarArraySelection) &&
				self::isSameObject($currentObjectDefinition, $selectionForObject) &&
				!isset($selectionNameToSelection[$selection->getName()])) {
				$selectionNameToSelection[$selection->getName()] = clone $selection;
			} elseif ($selection instanceof FieldRepresentationArraySelection) {
				if (self::isSameObject($currentObjectDefinition, $selectionForObject)) {
					$name = $selection->getName();
					if (!isset($selectionNameToSelection[$name])) {
						$selectionNameToSelection[$name] = new FieldRepresentationArraySelectionBuilder(
							$selection->getFieldDefinition(),
							$selection->getReferencedRepresentationDefinition()
						);
					}

					/** @var FieldRepresentationArraySelectionBuilder $representationArraySelectionBuilder */
					$representationArraySelectionBuilder = $selectionNameToSelection[$name];
					$representationArraySelectionBuilder->append($selection);
				}
			} elseif ($selection instanceof RelationshipSelection) {
				if (self::isSameObject($currentObjectDefinition, $selectionForObject)) {
					if (!isset($selectionNameToSelection[$selection->getRelationshipName()])) {
						$selectionNameToSelection[$selection->getRelationshipName()] = clone $selection;
					} else {
						$selectionNameToSelection[$selection->getRelationshipName()]->combineRelationshipSelections(
							$selection
						);
					}
				} else {
					self::collectSelectionsMatchingObjectDefinition(
						array_merge(
							$selection->getFieldSelections(),
							$selection->getChildRelationshipSelections(),
							$selection->getCollectionSelections()
						),
						$selection->getReferencedObjectDefinition(),
						$selectionForObject,
						$selectionNameToSelection
					);
				}
			} elseif ($selection instanceof ObjectCollectionSelection) {
				if (self::isSameObject($currentObjectDefinition, $selectionForObject)) {
					if (!isset($selectionNameToSelection[$selection->getCollectionName()])) {
						$selectionNameToSelection[$selection->getCollectionName()] = new ObjectCollectionSelectionBuilder(
							$selection->getCollectionDefinition(),
							$selection->getReferencedObjectDefinition()
						);
					}

					/** @var ObjectCollectionSelectionBuilder $objectCollectionSelectionBuilder */
					$objectCollectionSelectionBuilder = $selectionNameToSelection[$selection->getCollectionName()];
					$objectCollectionSelectionBuilder->append($selection);
				}

				self::collectSelectionsMatchingObjectDefinition(
					array_merge(
						$selection->getFieldSelections(),
						$selection->getRelationshipSelections(),
						$selection->getCollectionSelections()
					),
					$selection->getReferencedObjectDefinition(),
					$selectionForObject,
					$selectionNameToSelection
				);

			} elseif ($selection instanceof ScalarCollectionSelection &&
				self::isSameObject($currentObjectDefinition, $selectionForObject) &&
				!isset($selectionNameToSelection[$selection->getCollectionName()])) {
				$selectionNameToSelection[$selection->getCollectionName()] = clone $selection;
			} elseif ($selection instanceof RepresentationCollectionSelection) {
				if (self::isSameObject($currentObjectDefinition, $selectionForObject)) {
					if (!isset($selectionNameToSelection[$selection->getCollectionName()])) {
						$selectionNameToSelection[$selection->getCollectionName()] = new RepresentationCollectionSelectionBuilder(
							$selection->getCollectionDefinition(),
							$selection->getReferencedRepresentationDefinition()
						);
					}

					/** @var RepresentationCollectionSelectionBuilder $representationCollectionSelectionBuilder */
					$representationCollectionSelectionBuilder = $selectionNameToSelection[$selection->getCollectionName()];
					$representationCollectionSelectionBuilder->append($selection);
				}

				$selectionNameToSelection[$selection->getCollectionName()] = clone $selection;
			}
		}
	}

	private static function isSameObject(ObjectDefinition $objectDefinition1, ObjectDefinition $objectDefinition2): bool
	{
		return strcasecmp($objectDefinition1->getType(), $objectDefinition2->getType()) === 0;
	}
}

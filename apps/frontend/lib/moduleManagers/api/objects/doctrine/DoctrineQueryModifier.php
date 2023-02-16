<?php
namespace Api\Objects\Doctrine;

use Api\DataTypes\ConversionContext;
use Api\DataTypes\DataType;
use Api\DataTypes\ValueConverter;
use Api\Framework\ClassInstantiator;
use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Collections\ObjectCollectionSelection;
use Api\Objects\Collections\RepresentationCollectionSelection;
use Api\Objects\Collections\RepresentationReferenceSelection;
use Api\Objects\Collections\ScalarCollectionSelection;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\Selections\FieldRepresentationArraySelection;
use Api\Objects\Query\Selections\FieldScalarArraySelection;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Representations\RepresentationPropertyDefinition;
use Doctrine_Collection;
use Doctrine_Exception;
use Doctrine_Query;
use Doctrine_Record;
use Doctrine_Record_Exception;
use RuntimeException;
use sfContext;

/**
 * Integration from Doctrine queries to Object framework that provides functions to modify either the inputs of the
 * query or the results of the query.
 *
 * Class DoctrineQueryModifier
 * @package Api\Objects\Doctrine
 */
class DoctrineQueryModifier
{
	/** @var ObjectDefinition $objectDefinition */
	private $objectDefinition;
	private ClassInstantiator $classInstantiator;

	public function __construct(ObjectDefinition $objectDefinition)
	{
		$this->objectDefinition = $objectDefinition;

		/** @var ClassInstantiator $classInstantiator */
		$classInstantiator = sfContext::getInstance()->getContainer()->get('api.framework.classInstantiator');
		$this->classInstantiator = $classInstantiator;
	}

	/**
	 * Creates a new Doctrine query that is associated to this object with the selected fields.
	 * @param QueryContext $queryContext
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 * @return Doctrine_Query
	 */
	public function createDoctrineQuery(QueryContext $queryContext,array $selections): Doctrine_Query
	{
		$rootQueryBuilderNode = new QueryBuilderNode();
		$this->modifyQueryBuilderWithSelections($rootQueryBuilderNode, $selections);

		// get the Doctrine_Table instance related to the object.
		$objectDef = $this->getObjectDefinition();
		$doctrineTable = $objectDef->getDoctrineTable();
		$primaryTableAlias = 'v';
		$query = $doctrineTable->createQuery($primaryTableAlias);
		$rootQueryBuilderNode->applyToDoctrineQuery($query, $primaryTableAlias);

		$query->addWhere('account_id = ?', $queryContext->getAccountId());
		return $query;
	}

	/**
	 * Modifies the given {@see QueryBuilderNode} with the given array of selections.
	 * @param QueryBuilderNode $queryBuilderNode
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 */
	final public function modifyQueryBuilderWithSelections(QueryBuilderNode $queryBuilderNode, array $selections): void
	{
		/** @var BulkDataProcessor[] $bulkDataProcessorsById */
		$bulkDataProcessorsById = [];

		// Add all of the selections made to the Doctrine query
		foreach ($selections as $selection) {
			if ($selection instanceof FieldDefinition || $selection instanceof FieldScalarSelection || $selection instanceof FieldScalarArraySelection) {
				if ($selection instanceof FieldDefinition) {
					$fieldDefinition = $selection;
				} else {
					$fieldDefinition = $selection->getFieldDefinition();
				}

				if ($fieldDefinition->isBulkField()) {
					$processorName = $fieldDefinition->getBulkDataProcessorClass();
					if (!isset($bulkDataProcessorsById[$processorName])) {
						$bulkDataProcessorsById[$processorName] = $this->classInstantiator->instantiateFromId($processorName);
					}

					$bulkDataProcessorsById[$processorName]
						->modifyPrimaryQueryBuilder($this->objectDefinition, $fieldDefinition, $queryBuilderNode);
				} elseif ($fieldDefinition->isDerived()) {
					$this->modifyQueryBuilderWithDerivedFields($queryBuilderNode, [$fieldDefinition]);
				} elseif ($fieldDefinition->isCustom()) {
					// do nothing for this case
				} else {
					$queryBuilderNode->addSelection($fieldDefinition->getDoctrineField());
				}
			} elseif ($selection instanceof FieldRepresentationArraySelection) {
				$fieldDefinition = $selection->getFieldDefinition();
				$processorName = $fieldDefinition->getBulkDataProcessorClass();
				if (!isset($bulkDataProcessorsById[$processorName])) {
					$bulkDataProcessorsById[$processorName] = $this->classInstantiator->instantiateFromId($processorName);
				}

				$bulkDataProcessorsById[$processorName]
					->modifyPrimaryQueryBuilder($this->objectDefinition, $selection, $queryBuilderNode);
			} elseif ($selection instanceof RelationshipSelection) {
				$selection->apply($queryBuilderNode);
			} elseif ($selection instanceof CollectionSelection) {
				$processorName = $selection->getBulkDataProcessorClass();
				if (!isset($bulkDataProcessorsById[$processorName])) {
					$bulkDataProcessorsById[$processorName] = $this->classInstantiator->instantiateFromId($processorName);
				}

				$bulkDataProcessorsById[$processorName]
					->modifyPrimaryQueryBuilder($this->objectDefinition, $selection, $queryBuilderNode);
			} else {
				throw new RuntimeException('Unknown selection type specified');
			}
		}
	}

	/**
	 * Override this method to add any additional fields or join for the derived fields selected by the user. Usually
	 * getValueForDerivedField will also need to be overridden to handle calculating the derived field's value.
	 *
	 * @param QueryBuilderNode $queryBuilderNode
	 * @param FieldDefinition[] $derivedFieldDefinitions
	 */
	public function modifyQueryBuilderWithDerivedFields(QueryBuilderNode $queryBuilderNode, array $derivedFieldDefinitions): void
	{
		foreach ($derivedFieldDefinitions as $fieldDef) {
			throw new RuntimeException("Unhandled derived field: {$this->getObjectDefinition()->getType()}.{$fieldDef->getName()}.");
		}
	}

	/**
	 * Converts a Doctrine collection into an array of fields specified in the selection and database values. Database
	 * values are unconverted.
	 *
	 * The returned result will have keys that are the API names of the fields at a specific version and the values are
	 * from the DB (before conversion). The reason is that other processes, like bulk data, can be loaded into the
	 * array and the conversion from DB to API or DB to server can be performed in a single pass across the full array.
	 *
	 * For example, given a selection like "createdAt", which is a field with Doctrine name "created_at", the returned
	 * array will contain a key of "createdAt" with value of a string containing the MySQL formatted date.
	 *
	 * <code>
	 * [
	 *   [ "createdAt => "2020-04-20 00:00:00" ]
	 * ]
	 * </code>
	 *
	 * @param int $version
	 * @param Doctrine_Collection $doctrineCollection
	 * @param FieldDefinition[]|FieldSelection[]|RelationshipSelection[] $selections The selections requested to be returned.
	 * @return array
	 * @throws Doctrine_Exception
	 */
	final public function convertDoctrineCollectionToDatabaseArrays(
		int $version,
		Doctrine_Collection $doctrineCollection,
		array $selections
	): array {
		$convertedResults = [];

		/** @var \sfDoctrineRecord $doctrineRecord */
		foreach ($doctrineCollection as $doctrineRecord) {
			$convertedResults[] = $this->convertDoctrineRecordToDatabaseArray(
				$version,
				$doctrineRecord,
				$selections
			);
		}

		return $convertedResults;
	}

	/**
	 * Converts an array of DB values into an array containing only the fields specified and the values in the "api" format.
	 *
	 * The input argument is expected to have keys that are the API names of the fields at a specific version and the
	 * values formatted as if they came from the DB. The selections are then used to traverse the arrays to convert to
	 * the "api" format for each data type.
	 *
	 * @param int $version
	 * @param array[] $dbRecords The array of DB records to be converted.
	 * @param FieldDefinition[]|FieldSelection[]|RelationshipSelection[] $selections
	 * @param ConversionContext $conversionContext
	 * @return array
	 */
	final public function convertDatabaseArraysToApiArrays(int $version, array $dbRecords, array $selections, ConversionContext $conversionContext): array
	{
		$valueConverter = ValueConverter::createDbToApiValueConverter($conversionContext);
		return $this->convertFromDatabaseArrays($version, $dbRecords, $selections, $valueConverter);
	}

	/**
	 * Converts an array of DB values into an array containing only the fields specified and the values in the "server" format.
	 *
	 * The input argument is expected to have keys that are the API names of the fields at a specific version and the
	 * values formatted as if they came from the DB. The selections are then used to traverse the arrays to convert to
	 * the "server" format for each data type.
	 *
	 * @param int $version
	 * @param array[] $dbRecords The array of DB records to be converted.
	 * @param FieldDefinition[]|FieldSelection[]|RelationshipSelection[] $selections
	 * @return array
	 */
	final public function convertDatabaseArraysToServerArrays(int $version, array $dbRecords, array $selections): array
	{
		$valueConverter = ValueConverter::createDbToServerValueConverter();
		return $this->convertFromDatabaseArrays($version, $dbRecords, $selections, $valueConverter);
	}

	private function convertFromDatabaseArrays(
		int $version,
		array $dbRecords,
		array $selections,
		ValueConverter $valueConverter
	): array {
		$results = [];
		foreach ($dbRecords as $dbRecord) {
			if (!is_null($dbRecord)) {
				$results[] = $this->convertFromDatabaseArray($version, $dbRecord, $selections, $valueConverter);
			}
		}
		return $results;
	}

	private function convertFromDatabaseArray(
		int $version,
		array $dbRecord,
		array $selections,
		ValueConverter $valueConverter
	): array {
		$result = [];
		foreach ($selections as $selection) {
			if ($selection instanceof FieldDefinition || $selection instanceof FieldScalarSelection || $selection instanceof FieldScalarArraySelection) {
				if ($selection instanceof FieldDefinition) {
					$fieldDefinition = $selection;
				} else {
					$fieldDefinition = $selection->getFieldDefinition();
				}
				$fieldName = $fieldDefinition->getName();
				$result[$fieldName] = $this->convertArrayValueFromDatabaseValue(
					$dbRecord,
					$fieldName,
					$fieldDefinition->getDataType(),
					$valueConverter
				);
			} elseif ($selection instanceof FieldRepresentationArraySelection) {
				$fieldName = $selection->getFieldDefinition()->getName();
				if (!array_key_exists($fieldName, $dbRecord)) {
					// The user selected an array of representations but none of the BulkDataProcessor instances populated a value.
					$result[$fieldName] = null;
				} else if (is_null($dbRecord[$fieldName])) {
					$result[$fieldName] = null;
				} else {
					$result[$fieldName] = $this->convertFromDatabaseArrays(
						$version,
						$dbRecord[$fieldName],
						array_merge(
							$selection->getRepresentationSelection()->getProperties(),
							$selection->getRepresentationSelection()->getRepresentationReferenceSelections()
						),
						$valueConverter
					);
				}
			} elseif ($selection instanceof RelationshipSelection) {
				$relationshipName = $selection->getRelationship()->getName();
				if (!array_key_exists($relationshipName, $dbRecord)) {
					// The user selected a relationship but none of the BulkDataProcessor instances populated a value.
					$result[$relationshipName] = null;
				} elseif (is_null($dbRecord[$relationshipName])) {
					$result[$relationshipName] = null;
				} else {
					$result[$relationshipName] = $this->convertFromDatabaseArray(
						$version,
						$dbRecord[$relationshipName],
						array_merge(
							$selection->getFieldSelections(),
							$selection->getChildRelationshipSelections(),
							$selection->getCollectionSelections()
						),
						$valueConverter
					);
				}
			} elseif ($selection instanceof ScalarCollectionSelection) {
				$collectionName = $selection->getCollectionName();
				if (!array_key_exists($collectionName, $dbRecord)) {
					// The user selected a collection but none of the BulkDataProcessor instances populated a value.
					$result[$collectionName] = null;
				} else if (is_null($dbRecord[$collectionName])) {
					$result[$collectionName] = null;
				} else {
					$collectionResults = [];
					foreach ($dbRecord[$collectionName] as $collectionItem) {
						$collectionResults[] = $valueConverter->convert($selection->getDataType(), $collectionItem);
					}
					$result[$collectionName] = $collectionResults;
				}
			} elseif ($selection instanceof ObjectCollectionSelection) {
				$collectionName = $selection->getCollectionName();
				if (!array_key_exists($collectionName, $dbRecord)) {
					// The user selected a collection but none of the BulkDataProcessor instances populated a value.
					$result[$collectionName] = null;
				} else if (is_null($dbRecord[$collectionName])) {
					$result[$collectionName] = null;
				} else {
					$result[$collectionName] = $this->convertFromDatabaseArrays(
						$version,
						$dbRecord[$collectionName],
						array_merge(
							$selection->getFieldSelections(),
							$selection->getRelationshipSelections(),
							$selection->getCollectionSelections()
						),
						$valueConverter
					);
				}
			} elseif ($selection instanceof RepresentationCollectionSelection) {
				$collectionName = $selection->getCollectionName();
				if (!array_key_exists($collectionName, $dbRecord)) {
					// The user selected a collection but none of the BulkDataProcessor instances populated a value.
					$result[$collectionName] = null;
				} elseif (is_null($dbRecord[$collectionName])) {
					$result[$collectionName] = null;
				} else {
					$result[$collectionName] = $this->convertFromDatabaseArrays(
						$version,
						$dbRecord[$collectionName],
						array_merge(
							$selection->getProperties(),
							$selection->getRepresentationReferenceSelections()
						),
						$valueConverter
					);
				}
			} elseif ($selection instanceof RepresentationPropertyDefinition) {
				$fieldName = $selection->getName();
				$result[$fieldName] = $this->convertArrayValueFromDatabaseValue(
					$dbRecord,
					$fieldName,
					$selection->getDataType(),
					$valueConverter
				);
			} elseif ($selection instanceof RepresentationReferenceSelection) {
				$propertyName = $selection->getPropertyName();
				if (!array_key_exists($propertyName, $dbRecord)) {
					// The user selected the property but none of the BulkDataProcessor instances populated a value.
					$result[$propertyName] = null;
				} elseif (is_null($dbRecord[$propertyName])) {
					$result[$propertyName] = null;
				} else {
					$result[$propertyName] = $this->convertFromDatabaseArray(
						$version,
						$dbRecord[$propertyName],
						array_merge(
							$selection->getRepresentationSelection()->getProperties(),
							$selection->getRepresentationSelection()->getRepresentationReferenceSelections()
						),
						$valueConverter
					);
				}
			} else {
				throw new RuntimeException('Unknown selection type specified: ' . get_class($selection));
			}
		}

		return $result;
	}

	/**
	 * @param array $dbRecord
	 * @param string $name
	 * @param DataType $dataType
	 * @param ValueConverter $valueConverter
	 * @return mixed|null
	 */
	private function convertArrayValueFromDatabaseValue(
		array $dbRecord,
		string $name,
		DataType $dataType,
		ValueConverter $valueConverter
	) {
		$dbValue = $dbRecord[$name] ?? null;
		if (is_null($dbValue)) {
			return null;
		}
		return $valueConverter->convert($dataType, $dbValue);
	}

	/**
	 * Converts a Doctrine collection into an array containing only the specified fields and the values in the "server"
	 * format (eg. dates are DateTime instances instead of strings).
	 * @param int $version
	 * @param Doctrine_Collection $doctrineCollection
	 * @param FieldDefinition[]|RelationshipSelection[] $selections
	 * @return array
	 * @throws Doctrine_Exception
	 */
	final public function convertDoctrineCollectionToServerValue(
		int $version,
		Doctrine_Collection $doctrineCollection,
		array $selections
	): array {
		$dbArrays = $this->convertDoctrineCollectionToDatabaseArrays($version, $doctrineCollection, $selections);
		return $this->convertDatabaseArraysToServerArrays($version, $dbArrays, $selections);
	}

	/**
	 * Converts a Doctrine collection into an array containing only the specified fields and the values in the "api"
	 * format (eg. dates are formatted strings returned by the API).
	 * @param int $version
	 * @param Doctrine_Collection $doctrineCollection
	 * @param RelationshipSelection[]|FieldDefinition[] $selections The selections requested to be returned.
	 * @param ConversionContext $conversionContext
	 * @return array
	 * @throws Doctrine_Exception
	 */
	final public function convertDoctrineCollectionToApiArrays(
		int $version,
		Doctrine_Collection $doctrineCollection,
		array $selections,
		ConversionContext $conversionContext
	): array {
		$dbArrays = $this->convertDoctrineCollectionToDatabaseArrays($version, $doctrineCollection, $selections);
		return $this->convertDatabaseArraysToApiArrays($version, $dbArrays, $selections, $conversionContext);
	}

	/**
	 * @param int $version
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition[]|FieldSelection[]|RelationshipSelection[] $selections
	 * @return array
	 * @throws Doctrine_Exception
	 */
	final public function convertDoctrineRecordToDatabaseArray(
		int $version,
		Doctrine_Record $doctrineRecord,
		array $selections
	): array {
		$result = [];
		foreach ($selections as $selection) {
			if ($selection instanceof FieldDefinition) {
				// Indirect fields (including custom fields) are processed separately
				if ($selection->isBulkField() || $selection->isCustom()) {
					continue;
				}

				$dbValue = $this->getDatabaseValueFromDoctrineRecord($doctrineRecord, $selection);
				$result[$selection->getName()] = $dbValue;
			} elseif ($selection instanceof FieldRepresentationArraySelection) {
				// Representation Arrays are skipped because they are added in a later in the bulk data processor step

			} elseif ($selection instanceof FieldScalarSelection || $selection instanceof FieldScalarArraySelection) {
				// Bulk fields and custom fields are processed separately
				if ($selection->isBulkField() || $selection->isCustom()) {
					continue;
				}

				$dbValue = $this->getDatabaseValueFromDoctrineRecord($doctrineRecord, $selection->getFieldDefinition());
				$result[$selection->getName()] = $dbValue;
			} elseif ($selection instanceof RelationshipSelection) {
				if (!$selection->getRelationship()->isBulkRelationship()) {
					// skip bulk relationships so that we know to load them later
					$result[$selection->getRelationship()->getName()] = $this
						->convertRelationshipDoctrineRecordToDatabaseArray($version, $selection, $doctrineRecord);
				}
			} elseif ($selection instanceof CollectionSelection) {
				// Collections are skipped because they are not found on the primary record and are added in with a bulk
				// data processor in a later step.
			} else {
				throw new RuntimeException('Unknown selection type specified');
			}
		}

		return $result;
	}

	/**
	 * @param int $version
	 * @param RelationshipSelection $relationshipSelection
	 * @param Doctrine_Record|null $doctrineRecord
	 * @return array|null
	 * @throws Doctrine_Exception
	 */
	private function convertRelationshipDoctrineRecordToDatabaseArray(
		int $version,
		RelationshipSelection $relationshipSelection,
		?Doctrine_Record $doctrineRecord
	): ?array {
		if ($relationshipSelection->getRelationship()->isBulkRelationship()) {
			throw new RuntimeException('Unable to convert bulk relationships');
		}
		if (is_null($doctrineRecord)) {
			return null;
		}

		$relationshipDoctrineName = $relationshipSelection->getRelationship()->getDoctrineName();
		$relationshipRecord = $doctrineRecord->get($relationshipDoctrineName);
		if (is_null($relationshipRecord)) {
			return null;
		}

		$referencedObjectDefinition = $relationshipSelection->getReferencedObjectDefinition();
		$queryModifier = $referencedObjectDefinition->getDoctrineQueryModifier();
		$result = $queryModifier->convertDoctrineRecordToDatabaseArray(
			$version,
			$relationshipRecord,
			$relationshipSelection->getFieldSelections()
		);

		foreach ($relationshipSelection->getChildRelationshipSelections() as $childRelationshipSelection) {
			// skip bulk relationships since we will load them later
			if ($childRelationshipSelection->getRelationship()->isBulkRelationship()) {
				continue;
			}

			$childObjectDefinition = $childRelationshipSelection->getReferencedObjectDefinition();
			$result[$childRelationshipSelection->getRelationship()->getName()] = $childObjectDefinition
				->getDoctrineQueryModifier()
				->convertRelationshipDoctrineRecordToDatabaseArray(
					$version,
					$childRelationshipSelection,
					$relationshipRecord
				);
		}

		return $result;
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDefinition
	 * @return mixed|null
	 * @throws Doctrine_Record_Exception
	 */
	private function getDatabaseValueFromDoctrineRecord(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDefinition)
	{
		// either calculate the value for a derived field or retrieve the value from the Doctrine record
		if ($fieldDefinition->isDerived()) {
			return $this->getValueForDerivedField($doctrineRecord, $fieldDefinition);
		} elseif (isset($doctrineRecord[$fieldDefinition->getDoctrineField()])) {
			return $doctrineRecord->get($fieldDefinition->getDoctrineField(), false);
		} else {
			// This should never happen since the Doctrine record should contain all fields requested
			return null;
		}
	}

	/**
	 * Override this method to calculate the value of a derived field based on the record returned from the query. This should
	 * include the fields that were added during modifyQueryWithDerivedFields.
	 *
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryBuilderWithDerivedFields
	 */
	protected function getValueForDerivedField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		throw new \RuntimeException("Unhandled derived field: {$this->getObjectDefinition()->getType()}.{$fieldDef->getName()}.");
	}

	final protected function getObjectDefinition(): ObjectDefinition
	{
		return $this->objectDefinition;
	}
}

<?php

namespace Api\Representations;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\DataTypes\StringDataType;
use Api\Objects\Collections\ObjectItemTypeDefinition;
use Api\Objects\Collections\RepresentationItemTypeDefinition;
use Api\Objects\Collections\ScalarItemTypeDefinition;
use Api\Objects\StaticObjectDefinition;
use Api\Objects\StaticObjectDefinitionCatalog;
use stringTools;
use RuntimeException;

class ObjectStaticRepresentationDefinitionCatalog implements StaticRepresentationDefinitionCatalog
{
	public const OBJECT_REP_NAME_SUFFIX = 'Representation';
	public const OBJECT_QUERY_RESULT_REP_NAME_SUFFIX = 'QueryResultCollectionRepresentation';

	/** @var StaticObjectDefinitionCatalog $staticObjectDefinitionCatalog */
	private StaticObjectDefinitionCatalog $staticObjectDefinitionCatalog;
	/** @var string[] $lowerNamesToNames */
	private ?array $lowerNamesToNames = null;
	/** @var StaticRepresentationDefinition[] $lowerNamesToRepresentationDefinitions */
	private array $lowerNamesToRepresentationDefinitions = [];


	public function __construct(StaticObjectDefinitionCatalog $staticObjectDefinitionCatalog)
	{
		$this->staticObjectDefinitionCatalog = $staticObjectDefinitionCatalog;
	}

	/**
	 * @inheritDoc
	 */
	public function getRepresentationNames(): array
	{
		$this->ensureRepresentationNames();
		return array_values($this->lowerNamesToNames);
	}

	/**
	 * @inheritDoc
	 */
	public function findRepresentationDefinitionByName(string $name)
	{
		$this->ensureRepresentationNames();

		if (array_key_exists(strtolower($name), $this->lowerNamesToRepresentationDefinitions)) {
			return $this->lowerNamesToRepresentationDefinitions[strtolower($name)];
		}

		if (!array_key_exists(strtolower($name), $this->lowerNamesToNames)) {
			return false;
		}

		if (stringTools::endsWith($name, self::OBJECT_QUERY_RESULT_REP_NAME_SUFFIX)) {
			$objectName = substr($name, 0, -strlen(self::OBJECT_QUERY_RESULT_REP_NAME_SUFFIX));
			$objectDefinition = $this->staticObjectDefinitionCatalog->findObjectDefinitionByObjectType($objectName);

			$newRepresentationDefinition = $this
				->createObjectQueryResultRepresentationDefinitionFromObjectDefinition($objectDefinition);
		} elseif (stringTools::endsWith($name, self::OBJECT_REP_NAME_SUFFIX)) {
			$objectName = substr($name, 0, -strlen(self::OBJECT_REP_NAME_SUFFIX));
			$objectDefinition = $this->staticObjectDefinitionCatalog->findObjectDefinitionByObjectType($objectName);

			$newRepresentationDefinition = $this
				->createObjectRepresentationDefinitionFromObjectDefinition($objectDefinition);
		} else {
			return false;
		}

		$this->lowerNamesToRepresentationDefinitions[strtolower($newRepresentationDefinition->getName())] = $newRepresentationDefinition;
		return $newRepresentationDefinition;
	}

	private function ensureRepresentationNames(): void
	{
		if (!is_null($this->lowerNamesToNames)) {
			return;
		}

		$this->lowerNamesToNames = [];

		foreach ($this->staticObjectDefinitionCatalog->getObjectNames() as $objectName) {
			$objectRepresentationName = self::createObjectRepresentationNameFromObjectName($objectName);
			$this->lowerNamesToNames[strtolower($objectRepresentationName)] = $objectRepresentationName;

			$objectQueryResultRepresentationName = $this
				->createObjectQueryResultRepresentationNameFromObjectName($objectName);
			$this->lowerNamesToNames[strtolower($objectQueryResultRepresentationName)] = $objectQueryResultRepresentationName;
		}
	}
	private function createObjectRepresentationDefinitionFromObjectDefinition(
		StaticObjectDefinition $objectDefinition
	): StaticRepresentationDefinition {
		$representationName = self::createObjectRepresentationNameFromObjectName($objectDefinition->getType());
		$representationProperties = [];

		foreach ($objectDefinition->getFields() as $fieldDefinition) {
			$isReadable = $fieldDefinition->isReadOnly() || !$fieldDefinition->isWriteOnly();
			$isWriteable = $fieldDefinition->isWriteOnly() || !$fieldDefinition->isReadOnly();

			$representationProperties[] = new RepresentationPropertyDefinition(
				$fieldDefinition->getName(),
				$fieldDefinition->getDataType(),
				$isReadable,
				$isWriteable,
				$fieldDefinition->isRequired()
			);
		}

		foreach ($objectDefinition->getRelationshipNames() as $relationshipName) {
			$relationshipDefinition = $objectDefinition->getRelationshipByName($relationshipName);
			$referenceRepresentationName = self::createObjectRepresentationNameFromObjectName($relationshipDefinition->getReferenceToDefinition()->getObjectName());
			$representationProperties[] = new RepresentationPropertyDefinition(
				$relationshipDefinition->getName(),
				new RepresentationReferenceDataType($referenceRepresentationName),
				true,
				false
			);
		}

		foreach ($objectDefinition->getCollectionNames() as $collectionName) {
			$collectionDefinition = $objectDefinition->getCollectionDefinitionByName($collectionName);
			$itemType = $collectionDefinition->getItemType();

			if ($itemType instanceof ObjectItemTypeDefinition) {
				$referencedObjectRepresentationName = self::createObjectRepresentationNameFromObjectName($itemType->getObjectType());
				$representationProperties[] = new RepresentationPropertyDefinition(
					$collectionName,
					new ArrayDataType(new RepresentationReferenceDataType($referencedObjectRepresentationName), 0),
					true,
					false
				);
			} elseif ($itemType instanceof RepresentationItemTypeDefinition) {
				$representationName = $itemType->getRepresentationName();
				$representationProperties[] = new RepresentationPropertyDefinition(
					$collectionName,
					new ArrayDataType(new RepresentationReferenceDataType($representationName), 0),
					true,
					false
				);
			} elseif ($itemType instanceof ScalarItemTypeDefinition) {
				$representationProperties[] = new RepresentationPropertyDefinition(
					$collectionName,
					new ArrayDataType($itemType->getDataType(), 0),
					true,
					false
				);
			} else {
				throw new RuntimeException("Unknown collection item type specified: " . get_class($itemType));
			}
		}

		return new StaticRepresentationDefinition(
			$representationName,
			$representationProperties,
			new ObjectCustomRepresentationPropertyProvider($objectDefinition),
			$objectDefinition
		);
	}

	public static function createObjectRepresentationNameFromObjectName(string $objectName): string
	{
		return $objectName . self::OBJECT_REP_NAME_SUFFIX;
	}

	private function createObjectQueryResultRepresentationDefinitionFromObjectDefinition(
		StaticObjectDefinition $objectDefinition
	): StaticRepresentationDefinition {
		$representationName = self::createObjectQueryResultRepresentationNameFromObjectName(
			$objectDefinition->getType()
		);
		$representationProperties = [];

		$objectRepresentationName = self::createObjectRepresentationNameFromObjectName($objectDefinition->getType());
		$representationProperties[] = new RepresentationPropertyDefinition(
			'values',
			new ArrayDataType(new RepresentationReferenceDataType($objectRepresentationName), 0),
			true,
			false
		);
		$representationProperties[] = new RepresentationPropertyDefinition(
			'nextPageToken',
			new StringDataType(),
			true,
			false
		);
		$representationProperties[] = new RepresentationPropertyDefinition(
			'nextPageUrl',
			new StringDataType(),
			true,
			false
		);

		return new StaticRepresentationDefinition(
			$representationName,
			$representationProperties,
			EmptyCustomRepresentationPropertyProvider::getInstance(),
			null
		);
	}

	public static function createObjectQueryResultRepresentationNameFromObjectName(string $objectName): string
	{
		return $objectName . self::OBJECT_QUERY_RESULT_REP_NAME_SUFFIX;
	}
}

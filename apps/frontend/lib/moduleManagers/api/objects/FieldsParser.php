<?php
namespace Api\Objects;

use AccountSettingsConstants;
use AccountSettingsManagerFactory;
use Api\DataTypes\ArrayDataType;
use Api\DataTypes\OneOfPrimitiveOrArrayOfPrimitiveDataType;
use Api\DataTypes\RepresentationReferenceDataType;
use Api\Exceptions\ApiException;
use Api\Framework\ApiRequest;
use Api\Objects\Access\ObjectAccessManager;
use Api\Objects\Collections\CollectionSelection;
use Api\Objects\Collections\ObjectCollectionSelection;
use Api\Objects\Collections\ObjectItemTypeDefinition;
use Api\Objects\Collections\RepresentationCollectionSelection;
use Api\Objects\Collections\RepresentationItemTypeDefinition;
use Api\Objects\Collections\RepresentationReferenceSelection;
use Api\Objects\Collections\RepresentationSelectionBuilder;
use Api\Objects\Collections\ScalarCollectionSelection;
use Api\Objects\Collections\ScalarItemTypeDefinition;
use Api\Objects\Query\Selections\FieldRepresentationArraySelection;
use Api\Objects\Query\Selections\FieldScalarArraySelection;
use Api\Objects\Query\Selections\FieldScalarSelection;
use Api\Objects\Query\Selections\FieldSelection;
use Api\Objects\Query\Selections\ObjectSelectionBuilder;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Representations\RepresentationDefinition;
use Api\Representations\RepresentationDefinitionCatalog;
use ApiErrorLibrary;
use RESTClient;
use RuntimeException;
use sfContext;

use Api\Objects\FieldParserSelectionErrors as SelectionErrorConstants;

class FieldsParser
{
	const MAXIMUM_RELATIONSHIP_DEPTH = 3;

	private ObjectDefinitionCatalog $objectDefinitionCatalog;
	private ObjectAccessManager $objectAccessManager;
	private RepresentationDefinitionCatalog $representationDefinitionCatalog;
	private AccountSettingsManagerFactory $accountSettingsManagerFactory;

	public function __construct(
		ObjectDefinitionCatalog $objectDefinitionCatalog = null,
		ObjectAccessManager $objectAccessManager = null,
		RepresentationDefinitionCatalog $representationDefinitionCatalog = null,
		AccountSettingsManagerFactory $accountSettingsManagerFactory = null
	) {
		$this->objectDefinitionCatalog = $objectDefinitionCatalog ?? ObjectDefinitionCatalog::getInstance();
		$this->objectAccessManager = $objectAccessManager ?? sfContext::getInstance()->getContainer()->get('api.objects.objectAccessManager');
		$this->representationDefinitionCatalog = $representationDefinitionCatalog ?? sfContext::getInstance()->getContainer()->get('api.representations.representationDefinitionCatalog');
		$this->accountSettingsManagerFactory = $accountSettingsManagerFactory ?? sfContext::getInstance()->getContainer()->get('accountSettings.accountSettingsManagerFactory');
	}

	/**
	 * @param ApiRequest $apiRequest
	 * @param string[] $selectionsFromRequest
	 * @param ObjectDefinition $objectDefinition
	 * @param bool $shouldAllowQueryableFieldSelection
	 * @param bool $shouldAllowCollectionSelection True if collections are allowed to be selected.
	 * @param bool $shouldAllowArrayCustomFieldSelection True if custom fields of arrays (like isMultipleResponseRecorded,
	 * checkbox, multi-select, etc) are allowed to be selected.
	 * @return array array of FieldDefinitions and RelationSelections
	 */
	public function parseFields(
		ApiRequest $apiRequest,
		array $selectionsFromRequest,
		ObjectDefinition $objectDefinition,
		bool $shouldAllowQueryableFieldSelection,
		bool $shouldAllowCollectionSelection = false,
		bool $shouldAllowArrayCustomFieldSelection = false
	): array {
		if (!$this->checkObjectPermissions($apiRequest, $objectDefinition)) {
			throw $this->createUnauthorizedException($objectDefinition->getType());
		}

		foreach ($selectionsFromRequest as $selectionName) {
			if (empty($selectionName) || ctype_space($selectionName)){
				throw $this->createEmptyFieldException();
			}
		}

		$selectionsFromRequest = $this->getUniqueFieldSelections($selectionsFromRequest);
		$selectionMap = [];
		$selectionsOverDepth = [];
		foreach ($selectionsFromRequest as $selectionName) {
			$selectionExplodedParts = explode('.', trim($selectionName));
			if (sizeof($selectionExplodedParts) > self::MAXIMUM_RELATIONSHIP_DEPTH+1) {
				$selectionsOverDepth[] = $selectionName;
				continue;
			}

			$this->buildFieldMap($selectionMap, $selectionName, ...$selectionExplodedParts);
		}

		if (!empty($selectionsOverDepth)) {
			throw $this->createMaximumDepthReachedException($selectionsOverDepth);
		}

		$selectionErrors = new FieldParserSelectionErrors();
		$objectSelectionBuilder = $this->createObjectSelectionBuilderFromFieldMap(
			$selectionMap,
			$objectDefinition,
			$apiRequest,
			$selectionErrors,
			$shouldAllowQueryableFieldSelection,
			$shouldAllowCollectionSelection,
			$shouldAllowArrayCustomFieldSelection
		);

		if (empty($selectionErrors) && !$objectSelectionBuilder) {
			// The expectation is that errors will be populated when the selection builder is not returned so if it's
			// empty something went wrong
			$currentSelections = $this->collectValuesFromFieldMaps($selectionMap);
			throw new RuntimeException("Expected errors to be found when the object selection failed.\nselections: " . join(", ", $currentSelections));
		}

		$this->throwExceptionFromSelectionsErrors($selectionErrors);

		return $objectSelectionBuilder->build()->toArray();
	}

	private function throwExceptionFromSelectionsErrors(FieldParserSelectionErrors $selectionErrors): void
	{
		// This is some tricky legacy logic! Any error that is not one of the special errors should fail first and then
		// special errors are handled afterwards.
		$invalidFields = $selectionErrors->getSelectionErrorsExcludingErrorCodes([
			SelectionErrorConstants::SELECTION_ERROR_QUERYABLE_NOT_ALLOWED,
			SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_NOT_ALLOWED,
			SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED,
			SelectionErrorConstants::SELECTION_ERROR_CUSTOM_FIELD_ARRAY_NOT_ALLOWED
		]);
		if (!empty($invalidFields)) {
			throw $this->createInvalidFieldException($invalidFields);
		}

		$nonQueryableFields = $selectionErrors->getSelectionErrorsWithErrorCodes([
			SelectionErrorConstants::SELECTION_ERROR_QUERYABLE_NOT_ALLOWED,
			// It doesn't make sense that the queryable errors are displayed for collections errors but this matches
			// the expected errors to the user.
			SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_NOT_ALLOWED,
			SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED,
		]);
		if (!empty($nonQueryableFields)) {
			throw $this->createNonValidRequestFieldException($nonQueryableFields);
		}

		$arrayCustomFields = $selectionErrors->getSelectionErrorsWithErrorCode(SelectionErrorConstants::SELECTION_ERROR_CUSTOM_FIELD_ARRAY_NOT_ALLOWED);
		if (!empty($arrayCustomFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				"fields. Custom fields that return array types are not allowed to be selected: " . implode(', ', $arrayCustomFields) . ".",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @param string[] $selectionsFromRequest
	 * @return string[]
	 */
	private function getUniqueFieldSelections(array $selectionsFromRequest): array
	{
		$lowerCaseSelectionToSelection = [];

		foreach ($selectionsFromRequest as $selectionFromRequest) {
			$lowerCaseSelectionToSelection[strtolower($selectionFromRequest)] = $selectionFromRequest;
		}

		return array_values($lowerCaseSelectionToSelection);
	}

	/**
	 * This implementation of the selection map (essentially a tree), uses the "value" field representing leave node as
	 * a string that corresponds to the value user selected and "children" that represents any sub collections for that
	 * field as well. For example, this would be the map:
	 * Campaign:
	 * 		value: Campaign.stringScalarCollection
	 * 		children: []
	 * userObjectCollection:
	 * 		value: null
	 * 		children:
	 * 			userObjectCollection:
	 * 				value: null
	 * 				children:
	 * 					id:
	 * 						value: Campaign.userObjectCollection.id
	 * 						children: []
	 * 					username:
	 * 						value: Campaign.userObjectCollection.username
	 * 						children: []
	 *
	 * @param array $selectionMap
	 * @param string $originalSelectionName
	 * @param string ...$selectionExplodedParts
	 */
	private function buildFieldMap(array &$selectionMap, string $originalSelectionName, string ...$selectionExplodedParts): void
	{
		$selectionName = strtolower(trim(array_shift($selectionExplodedParts)));
		if (!array_key_exists($selectionName, $selectionMap)) { // create the object if does not exist
			$selectionMap[$selectionName] = new FieldsParserMapHelper();
		}

		if (count($selectionExplodedParts) == 0) { // this means we got the last part already
			$selectionMap[$selectionName]->setValue($originalSelectionName);
			return;
		}

		$childSelectionMap = &$selectionMap[$selectionName]->getChildren();
		$this->buildFieldMap($childSelectionMap, $originalSelectionName, ...$selectionExplodedParts);
	}

	/**
	 * Builds a new {@see ObjectSelectionBuilder} from the given fields map.
	 *
	 * @param array $selectionMap
	 * @param ObjectDefinition $objectDefinition
	 * @param ApiRequest $apiRequest
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param bool $shouldAllowQueryableFieldSelection
	 * @param bool $shouldAllowCollectionSelection
	 * @param bool $shouldAllowArrayCustomFieldSelection
	 * @return ObjectSelectionBuilder|false The parsed selections or false when the selection fails. If failure, the
	 * invalidFields should be updated with the reason why.
	 */
	private function createObjectSelectionBuilderFromFieldMap(
		array $selectionMap,
		ObjectDefinition $objectDefinition,
		ApiRequest $apiRequest,
		FieldParserSelectionErrors $selectionErrors,
		bool $shouldAllowQueryableFieldSelection,
		bool $shouldAllowCollectionSelection,
		bool $shouldAllowArrayCustomFieldSelection
	) {
		$isValid = true;
		$objectSelectionBuilder = new ObjectSelectionBuilder($objectDefinition);

		/** @var FieldsParserMapHelper $subSelectionMap */
		foreach ($selectionMap as $selectionName => $subSelectionMap) {
			$isLeafNode = !is_null($subSelectionMap->getValue());
			$hasChildren = !empty($subSelectionMap->getChildren());

			// Handle fields that reference arrays
			$fieldRepArraySelection = $this->parseFieldRepresentationArraySelection(
				$apiRequest->getAccountId(),
				$objectDefinition,
				$selectionName,
				$subSelectionMap,
				$selectionErrors,
				$apiRequest
			);
			if ($fieldRepArraySelection === false) {
				$isValid = false;
				continue;
			} else if (!is_null($fieldRepArraySelection) && $fieldRepArraySelection instanceof FieldRepresentationArraySelection) {
				$objectSelectionBuilder->withFieldSelection($fieldRepArraySelection);
				continue;
			}

			// Handle field definitions
			$fieldSelection = $this->parseFieldFromSelection(
				$apiRequest->getAccountId(),
				$objectDefinition,
				$selectionName,
				$selectionErrors,
				$subSelectionMap,
				$shouldAllowQueryableFieldSelection,
				$shouldAllowArrayCustomFieldSelection
			);
			if ($fieldSelection === false) {
				$isValid = false;
				continue;
			} elseif (!is_null($fieldSelection) && $fieldSelection instanceof FieldSelection) {
				$objectSelectionBuilder->withFieldSelection($fieldSelection);
				continue;
			}

			// Handle scalar collection selection
			$scalarCollectionSelection = $this->parseScalarCollectionSelection(
				$objectDefinition,
				$selectionName,
				$subSelectionMap,
				$selectionErrors,
				$shouldAllowCollectionSelection
			);
			if ($scalarCollectionSelection === false) {
				$isValid = false;
				continue;
			} elseif (!is_null($scalarCollectionSelection) && $scalarCollectionSelection instanceof ScalarCollectionSelection) {
				$objectSelectionBuilder->withCollectionSelection($scalarCollectionSelection);
				continue;
			}

			// Handle relationship selection
			$relationshipSelection = $this->parseRelationshipSelection(
				$objectDefinition,
				$selectionName,
				$subSelectionMap,
				$selectionErrors,
				$apiRequest,
				$shouldAllowQueryableFieldSelection,
				$shouldAllowCollectionSelection,
				$shouldAllowArrayCustomFieldSelection
			);
			if ($relationshipSelection === false) {
				$isValid = false;
				continue;
			} elseif (!is_null($relationshipSelection) && $relationshipSelection instanceof RelationshipSelection) {
				$objectSelectionBuilder->withRelationshipSelection($relationshipSelection);
				continue;
			}

			// Handle object collection selection
			$collectionSelection = $this->parseObjectCollectionSelection(
				$objectDefinition,
				$selectionName,
				$subSelectionMap,
				$selectionErrors,
				$apiRequest,
				$shouldAllowQueryableFieldSelection,
				$shouldAllowCollectionSelection
			);
			if ($collectionSelection === false) {
				$isValid = false;
				continue;
			} elseif (!is_null($collectionSelection) && $collectionSelection instanceof CollectionSelection) {
				$objectSelectionBuilder->withCollectionSelection($collectionSelection);
				continue;
			}

			// Handle representation collection selection
			$collectionSelection = $this->parseRepresentationCollectionSelection(
				$objectDefinition,
				$selectionName,
				$subSelectionMap,
				$selectionErrors,
				$apiRequest,
				$shouldAllowCollectionSelection
			);
			if ($collectionSelection === false) {
				$isValid = false;
				continue;
			} elseif (!is_null($collectionSelection) && $collectionSelection instanceof CollectionSelection) {
				$objectSelectionBuilder->withCollectionSelection($collectionSelection);
				continue;
			}

			// None of the selections were found or fit our values
			// was a leaf node, but none of the leaves were found
			if ($isLeafNode && is_null($fieldSelection) && is_null($scalarCollectionSelection)) {
				$isValid = false;
				$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			}

			// was some sort of a relationship, but none of the fields were found
			if ($subSelectionMap->hasChildren() && is_null($relationshipSelection) && is_null($collectionSelection)) {
				$isValid = false;
				$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			}
		}

		if (!$isValid) {
			return false;
		}

		if ($objectSelectionBuilder->isEmpty()) {
			// For some reason, the selection is valid but there were no selections made so fail
			$currentSelections = $this->collectValuesFromFieldMaps($selectionMap);
			throw new RuntimeException("Expected at least one selection however no selections are returned.\nselections: " . join(", ", $currentSelections));
		}

		return $objectSelectionBuilder;
	}

	private function parseFieldRepresentationArraySelection(
		int $accountId,
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldsParserMapHelper $selectionMapForSelection,
		FieldParserSelectionErrors $selectionErrors,
		ApiRequest $apiRequest
	) {
		$fieldDefinition = $objectDefinition->getFieldByName($selectionName);

		// If the selection is not a field, then we can't handle it
		if (!$fieldDefinition) {
			return null;
		}

		$dataType = $fieldDefinition->getDataType();
		if (!$dataType instanceof ArrayDataType) {
			return null;
		}

		$itemType = $dataType->getItemDataType();
		if (!$itemType instanceof RepresentationReferenceDataType) {
			return null;
		}

		// array of representations should always have children.
		if (!$selectionMapForSelection->hasChildren()) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_MISSING_FIELD);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_MISSING_FIELD);
			return false;
		}

		$representationName = $itemType->getRepresentationName();
		$representationDefinition = $this->representationDefinitionCatalog->findRepresentationDefinitionByName(
			$apiRequest->getVersion(),
			$apiRequest->getAccountId(),
			$representationName
		);

		if (!$representationDefinition) {
			// For some reason the user specified a relationship that doesn't currently exist so return unknown selection
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			return false;
		}

		$representationSelection = $this->handleChildRepresentationCollectionSelection(
			$representationDefinition,
			$selectionName,
			$selectionMapForSelection->getChildren(),
			$selectionErrors,
			$apiRequest
		);
		if (!$representationSelection) {
			// Invalid properties are not added here since they were already added in the object selection handling
			return false;
		}

		return new FieldRepresentationArraySelection(
			$fieldDefinition,
			$representationSelection->build()
		);
	}

	/**
	 * @param int $accountId
	 * @param ObjectDefinition $objectDefinition
	 * @param string $selectionName
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param FieldsParserMapHelper $subSelectionMap
	 * @param bool $shouldAllowQueryableFieldSelection
	 * @param bool $shouldAllowArrayCustomFieldSelection
	 * @return FieldSelection|bool|null Returns the field definition if the selection is a field and has no child selections.
	 * False if the selection was a field definition but can't be selected. Null when the selection was not a field.
	 */
	private function parseFieldFromSelection(
		int $accountId,
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldParserSelectionErrors $selectionErrors,
		FieldsParserMapHelper $subSelectionMap,
		bool $shouldAllowQueryableFieldSelection,
		bool $shouldAllowArrayCustomFieldSelection
	) {
		$fieldDefinition = $objectDefinition->getFieldByName($selectionName);

		// If the selection is not a field, then we can't handle it
		if (!$fieldDefinition) {
			return null;
		}

		// Field selection cannot have any children selected
		// i.e. Fail when "firstName" is selected but selection is "firstName.lastName"
		if (count($subSelectionMap->getChildren()) > 0) {
			$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_SCALAR_WITH_INVALID_CHILD);
			return false;
		}

		// Write only fields are not allowed to be selected
		if ($fieldDefinition->isWriteOnly()) {
			$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_WRITE_ONLY_NOT_ALLOWED);
			return false;
		}

		// Write an invalid field message when the field is queryable but is not allowed in this context
		if ($shouldAllowQueryableFieldSelection && !$fieldDefinition->isQueryable()) {
			$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_QUERYABLE_NOT_ALLOWED);
			return false;
		}

		// Write an invalid field message when an array custom field but is not allowed in this context
		if (!$shouldAllowArrayCustomFieldSelection && $fieldDefinition->isCustom() &&
			($fieldDefinition->getDataType() instanceof ArrayDataType || $fieldDefinition->getDataType() instanceof OneOfPrimitiveOrArrayOfPrimitiveDataType)) {
			$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_CUSTOM_FIELD_ARRAY_NOT_ALLOWED);
			return false;
		}

		if ($fieldDefinition->getDataType() instanceof ArrayDataType) {
			return new FieldScalarArraySelection($fieldDefinition);
		}
		return new FieldScalarSelection($fieldDefinition);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param string $selectionName
	 * @param FieldsParserMapHelper $subSelectionMap
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param bool $shouldAllowCollectionSelection
	 * @return CollectionSelection|false|null Returns the collection selection if the selection is a valid scalar
	 * collection. False if the selection is a scalar collection but is not valid. Null when the selection is not a
	 * scalar collection.
	 */
	private function parseScalarCollectionSelection(
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldsParserMapHelper $subSelectionMap,
		FieldParserSelectionErrors $selectionErrors,
		bool $shouldAllowCollectionSelection
	) {
		$collectionDefinition = $objectDefinition->getCollectionDefinitionByName($selectionName);

		// The selection was not a collection so skip processing the selection.
		if (!$collectionDefinition) {
			return null;
		}

		// The selection was a collection but wasn't a scalar type so skip processing the selection.
		if (!($collectionDefinition->getItemType() instanceof ScalarItemTypeDefinition)) {
			return null;
		}

		if (!$shouldAllowCollectionSelection) {
			$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_NOT_ALLOWED);
			$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_NOT_ALLOWED);
			return false;
		}

		// Scalar collections cannot have any children selected
		// i.e. Fail when "suppressedListIds" is selected but selection is "suppressedListIds.name"
		if (count($subSelectionMap->getChildren()) > 0) {
			$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_SCALAR_COLLECTION_WITH_INVALID_CHILD);
			return false;
		}

		return new ScalarCollectionSelection($collectionDefinition);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param string $selectionName
	 * @param FieldsParserMapHelper $selectionMapForSelection
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param ApiRequest $apiRequest
	 * @param bool $shouldAllowQueryableFieldSelection
	 * @param bool $shouldAllowCollectionSelection
	 * @param bool $shouldAllowArrayCustomFieldSelection
	 * @return RelationshipSelection|false|null Returns the relationship selection if the selection is a valid
	 * relationship. False if the selection is a relationship collection but is not valid. Null when the selection is
	 * not a relationship selection.
	 */
	private function parseRelationshipSelection(
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldsParserMapHelper $selectionMapForSelection,
		FieldParserSelectionErrors $selectionErrors,
		ApiRequest $apiRequest,
		bool $shouldAllowQueryableFieldSelection,
		bool $shouldAllowCollectionSelection,
		bool $shouldAllowArrayCustomFieldSelection
	) {
		$relationshipDefinition = $objectDefinition->getRelationshipByName($selectionName);
		if (!$relationshipDefinition) {
			return null;
		}

		// selection referenced a relationship no children were selected so fail the selection.
		if (!$selectionMapForSelection->hasChildren()) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_MISSING_FIELD);
			return false;
		}

		$relationshipObjectName = $relationshipDefinition->getReferenceToDefinition()->getObjectName();
		$referencedObjectDefinition = $this->findObjectDefinition(
			$apiRequest->getVersion(),
			$apiRequest->getAccountId(),
			$relationshipObjectName,
			$apiRequest
		);

		// selection referenced a relationship but the object for the relationship couldn't be found so fail the selection.
		if (!$referencedObjectDefinition) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			return false;
		}

		// Relationships always point to an object so parse each of the subselections as if we are looking directly
		// at the object referenced by the relationship.
		$objectSelectionBuilder = $this->createObjectSelectionBuilderFromFieldMap(
			$selectionMapForSelection->getChildren(),
			$referencedObjectDefinition,
			$apiRequest,
			$selectionErrors,
			$shouldAllowQueryableFieldSelection,
			$shouldAllowCollectionSelection,
			$shouldAllowArrayCustomFieldSelection
		);

		// Parsing the object selection within this relationship failed so fail this relationship.
		if (!$objectSelectionBuilder) {
			// Invalid fields are not added here since they were already added in the object selection handling
			return false;
		}

		return new RelationshipSelection(
			$objectDefinition,
			$relationshipDefinition,
			$objectSelectionBuilder
		);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param string $selectionName
	 * @param FieldsParserMapHelper $selectionMapForSelection
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param ApiRequest $apiRequest
	 * @param bool $shouldAllowQueryableFieldSelection
	 * @param bool $shouldAllowCollectionSelection
	 * @return CollectionSelection|false|null Returns the collection selection if the selection is a valid object
	 * collection. False if the selection is an object collection but is not valid. Null when the selection is not an
	 * object collection.
	 */
	private function parseObjectCollectionSelection(
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldsParserMapHelper $selectionMapForSelection,
		FieldParserSelectionErrors $selectionErrors,
		ApiRequest $apiRequest,
		bool $shouldAllowQueryableFieldSelection,
		bool $shouldAllowCollectionSelection
	) {
		$collectionDefinition = $objectDefinition->getCollectionDefinitionByName($selectionName);
		if (!$collectionDefinition) {
			return null;
		}

		// collections within other collections not allowed
		if (!$shouldAllowCollectionSelection) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);
			return false;
		}

		// object collections should always have children.
		if (!$selectionMapForSelection->hasChildren()) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTION_MISSING_FIELD);
			return false;
		}

		$itemType = $collectionDefinition->getItemType();
		if (!$itemType instanceof ObjectItemTypeDefinition) {
			return null;
		}

		$objectType = $itemType->getObjectType();
		$referencedObjectDefinition = $this->findObjectDefinition(
			$apiRequest->getVersion(),
			$apiRequest->getAccountId(),
			$objectType,
			$apiRequest
		);

		if (!$referencedObjectDefinition) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			return false;
		}

		$objectSelectionBuilder = $this->createObjectSelectionBuilderFromFieldMap(
			$selectionMapForSelection->getChildren(),
			$referencedObjectDefinition,
			$apiRequest,
			$selectionErrors,
			$shouldAllowQueryableFieldSelection,
			false,
			false
		);

		// Parsing the object selection within this collection failed so fail full the collection
		if (!$objectSelectionBuilder) {
			// Invalid fields are not added here since they were already added in the object selection handling
			return false;
		}

		return new ObjectCollectionSelection($collectionDefinition, $objectSelectionBuilder->build());
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param string $selectionName
	 * @param FieldsParserMapHelper $selectionMapForSelection
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param ApiRequest $apiRequest
	 * @param bool $shouldAllowCollectionSelection
	 * @return CollectionSelection|false|null Returns the collection selection if the selection is a valid representation
	 * collection. False if the selection is a representation collection but is not valid. Null when the selection is
	 * not a representation collection.
	 */
	private function parseRepresentationCollectionSelection(
		ObjectDefinition $objectDefinition,
		string $selectionName,
		FieldsParserMapHelper $selectionMapForSelection,
		FieldParserSelectionErrors $selectionErrors,
		ApiRequest $apiRequest,
		bool $shouldAllowCollectionSelection
	) {
		$collectionDefinition = $objectDefinition->getCollectionDefinitionByName($selectionName);
		if (!$collectionDefinition) {
			return null;
		}

		// collections within other collections not allowed
		if (!$shouldAllowCollectionSelection) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);
			return false;
		}

		// representation collections should always have children.
		if (!$selectionMapForSelection->hasChildren()) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTION_MISSING_FIELD);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_COLLECTION_MISSING_FIELD);
			return false;
		}

		$itemType = $collectionDefinition->getItemType();
		if (!$itemType instanceof RepresentationItemTypeDefinition) {
			return null;
		}

		$representationName = $itemType->getRepresentationName();
		$representationDefinition = $this->representationDefinitionCatalog->findRepresentationDefinitionByName(
			$apiRequest->getVersion(),
			$apiRequest->getAccountId(),
			$representationName
		);

		if (!$representationDefinition) {
			$selectionErrors->addSelectionError($selectionMapForSelection->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			$this->addSelectionErrorsFromMap($selectionErrors, $selectionMapForSelection->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
			return false;
		}

		$representationSelection = $this->handleChildRepresentationCollectionSelection(
			$representationDefinition,
			$selectionName,
			$selectionMapForSelection->getChildren(),
			$selectionErrors,
			$apiRequest
		);
		if (!$representationSelection) {
			// Invalid fields are not added here since they were already added in the object selection handling
			return false;
		}

		return new RepresentationCollectionSelection(
			$collectionDefinition,
			$representationSelection->build()
		);
	}

	/**
	 * @param RepresentationDefinition $representationDefinition
	 * @param string $selectionName
	 * @param array $selectionMap
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param ApiRequest $apiRequest
	 * @return RepresentationSelectionBuilder|false Returns the representation collection or false when the representation
	 * collection is not valid.
	 */
	private function handleChildRepresentationCollectionSelection(
		RepresentationDefinition $representationDefinition,
		string $selectionName,
		array $selectionMap,
		FieldParserSelectionErrors $selectionErrors,
		ApiRequest $apiRequest
	) {
		$isValid = true;
		$representationSelectionBuilder = new RepresentationSelectionBuilder($representationDefinition);
		/** @var FieldsParserMapHelper $subSelectionMap */
		foreach ($selectionMap as $subSelectionName => $subSelectionMap) {
			$representationPropertyName = $subSelectionName;

			$propertyDefinition = $representationDefinition->getPropertyByName($representationPropertyName);

			// property not found
			if (is_null($propertyDefinition)) {
				$isValid = false;
				$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
				$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
				continue;
			}

			$propertyDataType = $propertyDefinition->getDataType();
			if ($propertyDataType instanceof ArrayDataType) {
				// Collections within collection not allowed
				$isValid = false;
				$selectionErrors->addSelectionError($subSelectionMap->getValue(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);
				$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_COLLECTIONS_WITHIN_COLLECTIONS_NOT_ALLOWED);

			} elseif ($propertyDataType instanceof RepresentationReferenceDataType) {
				// Handle properties that reference another representation
				$childRepresentation = $this->representationDefinitionCatalog->findRepresentationDefinitionByName(
					$apiRequest->getVersion(),
					$apiRequest->getAccountId(),
					$propertyDataType->getRepresentationName()
				);
				if (!$childRepresentation) {
					// Selected property referenced a representation that cannot be found so fail the selection.
					$isValid = false;
					$this->addSelectionErrorsFromMap($selectionErrors, $subSelectionMap->getChildren(), SelectionErrorConstants::SELECTION_ERROR_UNKNOWN);
					continue;
				}

				$childRepresentationSelection = $this->handleChildRepresentationCollectionSelection(
					$childRepresentation,
					$subSelectionName,
					$subSelectionMap->getChildren(),
					$selectionErrors,
					$apiRequest
				);
				if (!$childRepresentationSelection) {
					$isValid = false;
					continue;
				}

				$representationSelectionBuilder->withRepresentationReferenceSelection(
					new RepresentationReferenceSelection($propertyDefinition, $childRepresentationSelection->build())
				);

			} else {
				// Handling Scalar property
				if (!empty($subSelectionMap->getChildren())) {
					$isValid = false;
					$this->addSelectionErrorsFromMap(
						$selectionErrors,
						$subSelectionMap->getChildren(),
						SelectionErrorConstants::SELECTION_ERROR_SCALAR_COLLECTION_WITH_INVALID_CHILD
					);
					continue;
				}

				$representationSelectionBuilder->withProperty($propertyDefinition);
			}
		}
		if (!$isValid) {
			return false;
		}
		if ($representationSelectionBuilder->isEmpty()) {
			$currentSelections = $this->collectValuesFromFieldMaps($selectionMap);
			throw new RuntimeException("Expected at least one selection however no selections are returned.\nselections: " . join(", ", $currentSelections));
		}
		return $representationSelectionBuilder;
	}

	/**
	 * @param string $objectName
	 * @return ApiException
	 */
	private function createUnauthorizedException(string $objectName): ApiException
	{
		return new ApiException(
			ApiErrorLibrary::API_ERROR_ACCESS_DENIED,
			"Access to following object was denied: {$objectName}",
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * @return ApiException
	 */
	private function createEmptyFieldException(): ApiException
	{
		return new ApiException(
			ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
			"fields. Empty strings are not allowed.",
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * @param array $fieldNames
	 * @return ApiException
	 */
	private function createInvalidFieldException(array $fieldNames): ApiException
	{
		$fieldNames = array_unique($fieldNames);
		return new ApiException(
			ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
			"fields. It contains an invalid or unknown field: " . implode(', ', $fieldNames) . ".",
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * @param array $fieldNames
	 * @return ApiException
	 */
	private function createNonValidRequestFieldException(array $fieldNames): ApiException
	{
		$fieldNames = array_unique($fieldNames);
		return new ApiException(
			ApiErrorLibrary::API_ERROR_INVALID_FIELD_TYPES_IN_REQUEST,
			implode(', ', $fieldNames) . ". These fields are not queryable and should be removed from the request.",
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * @param array $fieldNames
	 * @return ApiException
	 */
	private function createMaximumDepthReachedException(array $fieldNames): ApiException
	{
		$fieldNames = array_unique($fieldNames);
		return new ApiException(
			ApiErrorLibrary::API_ERROR_RELATIONSHIP_MAXIMUM_DEPTH_REACHED,
			"Following fields are violating the constraint " . implode(', ', $fieldNames) . ".",
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $objectName
	 * @param ApiRequest $apiRequest
	 * @return bool|ObjectDefinition
	 */
	private function findObjectDefinition(
		int $version,
		int $accountId,
		string $objectName,
		ApiRequest $apiRequest
	) {
		$objectDefinition = $this->objectDefinitionCatalog->findObjectDefinitionByObjectType(
			$version,
			$accountId,
			$objectName
		);

		if ($objectDefinition && !$this->checkObjectPermissions($apiRequest, $objectDefinition)) {
			throw $this->createUnauthorizedException($objectName);
		}

		return $objectDefinition;
	}

	/**
	 * @param ApiRequest $apiRequest
	 * @param ObjectDefinition $objectDefinition
	 * @return bool
	 */
	private function checkObjectPermissions(ApiRequest $apiRequest, ObjectDefinition $objectDefinition): bool
	{
		return $this->objectAccessManager->canUserAccessObject(
			$apiRequest->getAccessContext(),
			$objectDefinition
		);
	}

	/**
	 * @param FieldParserSelectionErrors $selectionErrors
	 * @param FieldsParserMapHelper[] $selectionMap
	 * @param int $errorCode
	 */
	private function addSelectionErrorsFromMap(FieldParserSelectionErrors $selectionErrors, array $selectionMap, int $errorCode): void
	{
		$selections = $this->collectValuesFromFieldMaps($selectionMap);
		foreach ($selections as $selection) {
			$selectionErrors->addSelectionError($selection, $errorCode);
		}
	}

	/**
	 * Given an array of {@see FieldsParserMapHelper} instances, returns the "value" property of each and
	 * then recursively adds all children to the selections.
	 * @param FieldsParserMapHelper[] $fieldMapHelpers
	 * @return string[] The selections collected from the {@see FieldsParserMapHelper} instances.
	 */
	private function collectValuesFromFieldMaps(array $fieldMapHelpers): array
	{
		$selections = [];
		$this->collectValuesFromFieldMapsByRef($selections, $fieldMapHelpers);
		return $selections;
	}

	/**
	 * Given an array of {@see FieldsParserMapHelper} instances, adds the "value" property of each to the given array and
	 * then recursively adds all children to the selections.
	 * @param string[] $selections
	 * @param FieldsParserMapHelper[] $fieldMapHelpers
	 */
	private function collectValuesFromFieldMapsByRef(array &$selections, array $fieldMapHelpers)
	{
		foreach ($fieldMapHelpers as $subSelectionMap) {
			if (!is_null($subSelectionMap->getValue())) {
				$selections[] = $subSelectionMap->getValue();
			}

			$this->collectValuesFromFieldMapsByRef($selections, $subSelectionMap->getChildren());
		}
	}
}

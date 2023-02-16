<?php

namespace Api\Config\Objects\FormHandlerField;

use Api\Config\Objects\FormHandlerField\Gen\Doctrine\AbstractFormHandlerFieldDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\StaticObjectDefinitionCatalog;
use ApiFrameworkConstants;
use Doctrine_Record;
use RuntimeException;

class FormHandlerFieldDoctrineQueryModifier extends AbstractFormHandlerFieldDoctrineQueryModifier
{
	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 */
	protected function modifyQueryWithProspectApiFieldIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('prospect_field_default_id')
			->addSelection('piProspectFieldDefault', 'id')
			->addSelection('piProspectFieldDefault', 'field_id');

		$queryBuilderRoot->addSelection('prospect_field_custom_id')
			->addSelection('piProspectFieldCustom', 'id')
			->addSelection('piProspectFieldCustom', 'field_id');
	}

	/**
	 * @param \piFormHandlerFormField|Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return bool|null
	 */
	protected function getValueForProspectApiFieldIdField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		if($doctrineRecord->piProspectFieldCustom) {
			return $doctrineRecord->piProspectFieldCustom->field_id . ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX;
		}

		if ($doctrineRecord->piProspectFieldDefault) {
			$doctrineFieldId = $doctrineRecord->piProspectFieldDefault->field_id;

			// Verify that the Prospect Default Field is valid and return its respective field name
			$objectDefinition = StaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType('Prospect');
			$fieldDefinitions = $objectDefinition->getFields();

			foreach ($fieldDefinitions as $fieldDefinition) {
				if ($fieldDefinition->isCustom() || $fieldDefinition->isWriteOnly()) {
					continue;
				}
				$fieldDoctrineValue = $fieldDefinition->getDoctrineField();
				if (strcmp($doctrineFieldId, $fieldDoctrineValue) === 0) {
					return $fieldDefinition->getName();
				}
			}

			throw new RuntimeException("The prospect default field was not found in the API for form handler form fields. This should never happen!");
		}
	}
}


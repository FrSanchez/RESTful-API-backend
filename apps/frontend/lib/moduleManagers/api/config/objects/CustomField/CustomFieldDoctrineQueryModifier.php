<?php

namespace Api\Config\Objects\CustomField;

use Api\Config\Objects\CustomField\Gen\Doctrine\AbstractCustomFieldDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;
use piProspectFieldCustom;
use ApiFrameworkConstants;

class CustomFieldDoctrineQueryModifier extends AbstractCustomFieldDoctrineQueryModifier
{
	protected function modifyQueryWithApiFieldIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection("field_id");
	}

	protected function getValueForApiFieldIdField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piProspectFieldCustom $doctrineRecord */
		return $doctrineRecord->field_id . ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX;
	}
}

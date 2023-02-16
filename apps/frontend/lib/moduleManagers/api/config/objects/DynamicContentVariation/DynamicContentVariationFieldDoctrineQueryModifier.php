<?php


namespace Api\Config\Objects\DynamicContentVariation;


use Api\Config\Objects\DynamicContentVariation\Gen\Doctrine\AbstractDynamicContentVariationDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;

class DynamicContentVariationFieldDoctrineQueryModifier extends AbstractDynamicContentVariationDoctrineQueryModifier
{
	protected function modifyQueryWithComparisonField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('piDynamicContent','based_on')
				->addSelection('comparison_value1')
				->addSelection('comparison_value2')
				->addSelection('comparison_operator');
	}

	protected function getValueForComparisonField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piDynamicContentVariation $doctrineRecord */
		return $doctrineRecord->getComparisonDescription(false);
	}

}

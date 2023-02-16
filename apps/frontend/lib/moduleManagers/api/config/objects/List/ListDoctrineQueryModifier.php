<?php
namespace Api\Config\Objects\Listx;

use Api\Config\Objects\Listx\Gen\Doctrine\AbstractListDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;
use piListx;

class ListDoctrineQueryModifier extends AbstractListDoctrineQueryModifier
{
	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function modifyQueryWithIsDynamicField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection("dynamic_list_id");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForIsDynamicField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListx $doctrineRecord */
		return !is_null($doctrineRecord->dynamic_list_id);
	}
}

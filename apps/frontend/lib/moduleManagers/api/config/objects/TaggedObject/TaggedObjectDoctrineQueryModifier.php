<?php
namespace Api\Config\Objects\TaggedObject;

use Api\Config\Objects\TaggedObject\Gen\Doctrine\AbstractTaggedObjectDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;

class TaggedObjectDoctrineQueryModifier extends AbstractTaggedObjectDoctrineQueryModifier
{
	protected function modifyQueryWithTagNameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('piTag', 'name');
	}

	protected function getValueForTagNameField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piTagObject $doctrineRecord */
		return $doctrineRecord->piTag->name ?? null;
	}
}

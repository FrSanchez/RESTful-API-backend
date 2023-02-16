<?php
namespace Api\Config\Objects\LifecycleStageProspect;

use Api\Config\Objects\LifecycleStageProspect\Gen\Doctrine\AbstractLifecycleStageProspectDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;

class LifecycleStageProspectDoctrineQueryModifier extends AbstractLifecycleStageProspectDoctrineQueryModifier
{

	protected function modifyQueryWithLifecycleStageNameField(QueryBuilderNode $queryBuilderNode, FieldDefinition $fieldDef): void
	{
		$queryBuilderNode
			->addSelection('piLifecycleStage', 'name');
	}

	protected function getValueForLifecycleStageNameField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piLifecycleStageProspect  $doctrineRecord*/
		return $doctrineRecord->piLifecycleStage ? $doctrineRecord->piLifecycleStage->getDisplayName() : null;
	}
}

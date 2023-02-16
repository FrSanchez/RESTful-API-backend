<?php
namespace Api\Config\Objects\ExternalActivity;
use Api\Config\Objects\ExternalActivity\Gen\Doctrine\AbstractExternalActivityDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;

class ExternalActivityDoctrineQueryModifier extends AbstractExternalActivityDoctrineQueryModifier
{
	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 */
	protected function modifyQueryWithProspectIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('piExternalActivityProspect', 'prospect_id');
	}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return int|mixed|null
	 */
	protected function getValueForProspectIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piExternalActivity $doctrineRecord */
		return $doctrineRecord->piExternalActivityProspect->prospect_id ?? null;
	}
}

<?php
namespace Api\Config\Objects\File;

use Api\Config\Objects\File\Gen\Doctrine\AbstractFileDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use piFilex;

class FileDoctrineQueryModifier extends AbstractFileDoctrineQueryModifier
{
	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithBitlyIsPersonalizedField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('piBitlyUrl', 'is_personalized')
			->addSelection('piBitlyUrl', 'id');
	}

	/**
	 * @param piFilex $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed|void
	 */
	protected function getValueForBitlyIsPersonalizedField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->is_personalized : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithBitlyShortUrlField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('piBitlyUrl', 'short_url')
			->addSelection('piBitlyUrl', 'id');
	}

	/**
	 * @param piFilex $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed|void
	 */
	protected function getValueForBitlyShortUrlField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->short_url : null;
	}
}

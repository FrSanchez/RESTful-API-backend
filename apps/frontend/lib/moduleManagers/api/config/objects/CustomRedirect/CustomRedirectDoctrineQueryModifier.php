<?php
namespace Api\Config\Objects\CustomRedirect;

use Api\Config\Objects\CustomRedirect\Gen\Doctrine\AbstractCustomRedirectDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;
use piCustomUrl;

class CustomRedirectDoctrineQueryModifier extends AbstractCustomRedirectDoctrineQueryModifier
{

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithBitlyIsPersonalizedField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('bitly_url_id')
			->addSelection('piBitlyUrl', 'id')
			->addSelection('piBitlyUrl', 'is_personalized');
	}

	/**
	 * @param piCustomUrl|Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return bool|null
	 */
	protected function getValueForBitlyIsPersonalizedField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->is_personalized : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithBitlyShortUrlField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('bitly_url_id')
			->addSelection('piBitlyUrl', 'id')
			->addSelection('piBitlyUrl', 'short_url');
	}

	/**
	 * @param piCustomUrl|Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return string|null
	 */
	protected function getValueForBitlyShortUrlField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->short_url : null;
	}
}

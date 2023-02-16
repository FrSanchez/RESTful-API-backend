<?php

namespace Api\Config\Objects\LandingPage;

use Api\Config\Objects\LandingPage\Gen\Doctrine\AbstractLandingPageDoctrineQueryModifier;

class LandingPageDoctrineQueryModifier extends AbstractLandingPageDoctrineQueryModifier
{

	protected function modifyQueryWithBitlyIsPersonalizedField(\Api\Objects\Doctrine\QueryBuilderNode $queryBuilderRoot, \Api\Objects\FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('piBitlyUrl', 'is_personalized')
			->addSelection('piBitlyUrl', 'id');
	}

	protected function getValueForBitlyIsPersonalizedField(\Doctrine_Record $doctrineRecord, \Api\Objects\FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->is_personalized : null;
	}

	protected function modifyQueryWithBitlyShortUrlField(\Api\Objects\Doctrine\QueryBuilderNode $queryBuilderRoot, \Api\Objects\FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('piBitlyUrl', 'short_url')
			->addSelection('piBitlyUrl', 'id');
	}

	protected function getValueForBitlyShortUrlField(\Doctrine_Record $doctrineRecord, \Api\Objects\FieldDefinition $fieldDef)
	{
		return $doctrineRecord->piBitlyUrl ? $doctrineRecord->piBitlyUrl->short_url : null;
	}
}

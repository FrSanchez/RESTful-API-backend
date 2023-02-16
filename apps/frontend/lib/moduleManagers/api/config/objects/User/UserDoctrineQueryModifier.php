<?php
namespace Api\Config\Objects\User;

use Api\Config\Objects\User\Gen\Doctrine\AbstractUserDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use piUser;

class UserDoctrineQueryModifier extends AbstractUserDoctrineQueryModifier
{

	protected function modifyQueryWithUsernameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('username');
	}

	protected function getValueForUsernameField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piUser $doctrineRecord */
		return $doctrineRecord->getRawUsername();
	}
}

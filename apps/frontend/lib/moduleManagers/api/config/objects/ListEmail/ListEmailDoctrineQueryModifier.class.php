<?php

namespace Api\Config\Objects\ListEmail;

use Api\Config\Objects\ListEmail\Gen\Doctrine\AbstractListEmailDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;
use piListEmail;
use EmailPeer;

class ListEmailDoctrineQueryModifier extends AbstractListEmailDoctrineQueryModifier
{
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		return parent::createDoctrineQuery($queryContext, $selections)
			->addWhere('client_type != ? OR client_type IS NULL', EmailPeer::AUTOMATIC);
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithHtmlMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('piEmailMessage', 'html_message');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForHtmlMessageField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListEmail $doctrineRecord */
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->html_message : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithSubjectField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('piEmailMessage', 'subject');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForSubjectField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListEmail $doctrineRecord */
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->subject : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithTextMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('piEmailMessage', 'text_message');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForTextMessageField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListEmail $doctrineRecord */
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->text_message : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithClientTypeField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('client_type');
	}

	/**
	 * @inheritDoc
	 *
	 * Making client_type a derived field instead of an Enum type because
	 * 	1. Some of the string values for client_type are repeated which is not allowed by EnumDataType::isValidateEnumArray
	 *  2. It is possible (although rare/unlikely) for this field to be null since there is no default value, which would break the enum usage
	 */
	protected function getValueForClientTypeField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListEmail $doctrineRecord */
		return EmailPeer::getClientTypeName($doctrineRecord->client_type);
	}

	protected function modifyQueryWithEmailTemplateIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('piEmailMessage', 'email_template_id');
	}

	protected function getValueForEmailTemplateIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piListEmail $doctrineRecord */
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->email_template_id : null;
	}
}

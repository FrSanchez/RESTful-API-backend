<?php
namespace Api\Config\Objects\Email;

use Api\Config\Objects\Email\Gen\Doctrine\AbstractEmailDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\Query\QueryContext;
use Api\Objects\FieldDefinition;
use Doctrine_Query;
use Doctrine_Record;
use piEmail;
use EmailPeer;

class EmailDoctrineQueryModifier extends AbstractEmailDoctrineQueryModifier
{
	/**
	 * @inheritDoc
	 */
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$query = parent::createDoctrineQuery($queryContext, $selections);
		return $query
			->addWhere('sent_at IS NOT NULL')
			->addWhere('is_sent = true')
			->addWhere('is_queued = false')
			->addWhere('is_being_processed = false')
			->andWhereIn('is_hidden', [0, 1]);
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithHtmlMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('email_message_id')
			->addSelection('piEmailMessage', 'html_message');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForHtmlMessageField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var  \piEmail $doctrineRecord*/
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->html_message : null;
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithTextMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection('email_message_id')
			->addSelection('piEmailMessage', 'text_message');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForTextMessageField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var  \piEmail $doctrineRecord*/
		return $doctrineRecord->piEmailMessage ? $doctrineRecord->piEmailMessage->text_message : null;
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
	protected function getValueForSubjectField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var  \piEmail $doctrineRecord*/
		return $doctrineRecord->getSubject();
	}

	protected function modifyQueryWithClientTypeField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection('client_type');
	}

	protected function getValueForClientTypeField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piEmail $doctrineRecord */
		return EmailPeer::getClientTypeName($doctrineRecord->client_type);
	}
}

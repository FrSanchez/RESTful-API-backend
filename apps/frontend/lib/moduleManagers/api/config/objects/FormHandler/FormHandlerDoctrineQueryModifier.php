<?php


namespace Api\Config\Objects\FormHandler;


use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Record;
use piFormHandler;

class FormHandlerDoctrineQueryModifier extends Gen\Doctrine\AbstractFormHandlerDoctrineQueryModifier
{

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithErrorLocationField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('error_location');
	}

	/**
	 * @param piFormHandler $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed|void
	 */
	protected function getValueForErrorLocationField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		if (empty($doctrineRecord->error_location)) {
			return "Referring URL";
		} else {
			return $doctrineRecord->error_location;
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithSuccessLocationField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('success_location');
	}

	/**
	 * @param piFormHandler $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed|void
	 */
	protected function getValueForSuccessLocationField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		if (empty($doctrineRecord->success_location)) {
			return "Referring URL";
		} else {
			return $doctrineRecord->success_location;
		}
	}
}

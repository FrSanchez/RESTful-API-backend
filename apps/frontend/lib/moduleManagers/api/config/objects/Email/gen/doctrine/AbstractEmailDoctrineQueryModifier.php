<?php
namespace Api\Config\Objects\Email\Gen\Doctrine;

use Api\Objects\FieldDefinition;
use Api\Objects\Doctrine\QueryBuilderNode;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
abstract class AbstractEmailDoctrineQueryModifier extends \Api\Objects\Doctrine\DoctrineQueryModifier
{
	/**
	 * Override this method to add any additional fields or join for the derived fields selected by the user. Usually
	 * getValueForDerivedField will also need to be overridden to handle calculating the derived field's value.
	 *
	 * @param QueryBuilderNode $queryBuilderNode
	 * @param FieldDefinition[] $derivedFieldDefinitions
	 */
	public function modifyQueryBuilderWithDerivedFields(QueryBuilderNode $queryBuilderNode, array $derivedFieldDefinitions): void
	{
		foreach ($derivedFieldDefinitions as $fieldDef) {
			switch ($fieldDef->getName()) {
				case 'clientType':
				case 'client_type':
					$this->modifyQueryWithClientTypeField($queryBuilderNode, $fieldDef);
					break;
				case 'htmlMessage':
				case 'html_message':
					$this->modifyQueryWithHtmlMessageField($queryBuilderNode, $fieldDef);
					break;
				case 'subject':
				
					$this->modifyQueryWithSubjectField($queryBuilderNode, $fieldDef);
					break;
				case 'textMessage':
				case 'text_message':
					$this->modifyQueryWithTextMessageField($queryBuilderNode, $fieldDef);
					break;
				default:
					throw new RuntimeException("Unhandled derived field: {$fieldDef->getName()}.");
			}
		}
			}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForDerivedField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		switch ($fieldDef->getName()) {
			case 'clientType':
			case 'client_type':
				return $this->getValueForClientTypeField($doctrineRecord, $fieldDef);
			case 'htmlMessage':
			case 'html_message':
				return $this->getValueForHtmlMessageField($doctrineRecord, $fieldDef);
			case 'subject':
			
				return $this->getValueForSubjectField($doctrineRecord, $fieldDef);
			case 'textMessage':
			case 'text_message':
				return $this->getValueForTextMessageField($doctrineRecord, $fieldDef);
			default:
				return parent::getValueForDerivedField($doctrineRecord, $fieldDef);
		}
			}

	/**
	 * Override this method to add any additional fields or joins to calculate the clientType field. You should also override
	 * the getValueForClientTypeField function to handle calculating the clientType value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForClientTypeField
	 */
	protected abstract function modifyQueryWithClientTypeField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithClientTypeField
	 */
	protected abstract function getValueForClientTypeField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the htmlMessage field. You should also override
	 * the getValueForHtmlMessageField function to handle calculating the htmlMessage value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForHtmlMessageField
	 */
	protected abstract function modifyQueryWithHtmlMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithHtmlMessageField
	 */
	protected abstract function getValueForHtmlMessageField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the subject field. You should also override
	 * the getValueForSubjectField function to handle calculating the subject value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForSubjectField
	 */
	protected abstract function modifyQueryWithSubjectField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithSubjectField
	 */
	protected abstract function getValueForSubjectField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the textMessage field. You should also override
	 * the getValueForTextMessageField function to handle calculating the textMessage value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForTextMessageField
	 */
	protected abstract function modifyQueryWithTextMessageField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithTextMessageField
	 */
	protected abstract function getValueForTextMessageField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

}

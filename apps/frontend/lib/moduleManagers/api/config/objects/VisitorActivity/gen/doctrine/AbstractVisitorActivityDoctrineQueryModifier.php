<?php
namespace Api\Config\Objects\VisitorActivity\Gen\Doctrine;

use Api\Objects\FieldDefinition;
use Api\Objects\Doctrine\QueryBuilderNode;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
abstract class AbstractVisitorActivityDoctrineQueryModifier extends \Api\Objects\Doctrine\DoctrineQueryModifier
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
				case 'details':
				
					$this->modifyQueryWithDetailsField($queryBuilderNode, $fieldDef);
					break;
				case 'emailTemplateId':
				case 'email_template_id':
					$this->modifyQueryWithEmailTemplateIdField($queryBuilderNode, $fieldDef);
					break;
				case 'listEmailId':
				case 'list_email_id':
					$this->modifyQueryWithListEmailIdField($queryBuilderNode, $fieldDef);
					break;
				case 'typeName':
				case 'type_name':
					$this->modifyQueryWithTypeNameField($queryBuilderNode, $fieldDef);
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
			case 'details':
			
				return $this->getValueForDetailsField($doctrineRecord, $fieldDef);
			case 'emailTemplateId':
			case 'email_template_id':
				return $this->getValueForEmailTemplateIdField($doctrineRecord, $fieldDef);
			case 'listEmailId':
			case 'list_email_id':
				return $this->getValueForListEmailIdField($doctrineRecord, $fieldDef);
			case 'typeName':
			case 'type_name':
				return $this->getValueForTypeNameField($doctrineRecord, $fieldDef);
			default:
				return parent::getValueForDerivedField($doctrineRecord, $fieldDef);
		}
			}

	/**
	 * Override this method to add any additional fields or joins to calculate the details field. You should also override
	 * the getValueForDetailsField function to handle calculating the details value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForDetailsField
	 */
	protected abstract function modifyQueryWithDetailsField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithDetailsField
	 */
	protected abstract function getValueForDetailsField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the emailTemplateId field. You should also override
	 * the getValueForEmailTemplateIdField function to handle calculating the emailTemplateId value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForEmailTemplateIdField
	 */
	protected abstract function modifyQueryWithEmailTemplateIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithEmailTemplateIdField
	 */
	protected abstract function getValueForEmailTemplateIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the listEmailId field. You should also override
	 * the getValueForListEmailIdField function to handle calculating the listEmailId value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForListEmailIdField
	 */
	protected abstract function modifyQueryWithListEmailIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithListEmailIdField
	 */
	protected abstract function getValueForListEmailIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

	/**
	 * Override this method to add any additional fields or joins to calculate the typeName field. You should also override
	 * the getValueForTypeNameField function to handle calculating the typeName value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForTypeNameField
	 */
	protected abstract function modifyQueryWithTypeNameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithTypeNameField
	 */
	protected abstract function getValueForTypeNameField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

}

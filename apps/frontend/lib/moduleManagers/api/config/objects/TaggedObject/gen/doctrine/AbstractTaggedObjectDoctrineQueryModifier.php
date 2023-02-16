<?php
namespace Api\Config\Objects\TaggedObject\Gen\Doctrine;

use Api\Objects\FieldDefinition;
use Api\Objects\Doctrine\QueryBuilderNode;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
abstract class AbstractTaggedObjectDoctrineQueryModifier extends \Api\Objects\Doctrine\DoctrineQueryModifier
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
				case 'tagName':
				case 'tag_name':
					$this->modifyQueryWithTagNameField($queryBuilderNode, $fieldDef);
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
			case 'tagName':
			case 'tag_name':
				return $this->getValueForTagNameField($doctrineRecord, $fieldDef);
			default:
				return parent::getValueForDerivedField($doctrineRecord, $fieldDef);
		}
			}

	/**
	 * Override this method to add any additional fields or joins to calculate the tagName field. You should also override
	 * the getValueForTagNameField function to handle calculating the tagName value.
	 *
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @see getValueForTagNameField
	 */
	protected abstract function modifyQueryWithTagNameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef);

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 * @see modifyQueryWithTagNameField
	 */
	protected abstract function getValueForTagNameField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef);

}
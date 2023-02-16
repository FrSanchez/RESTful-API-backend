<?php
namespace Api\Config\Objects\Form;

use Api\Config\Objects\Form\Gen\Doctrine\AbstractFormDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Doctrine_Exception;
use Doctrine_Record;
use FormPeer;
use piForm;
use RuntimeException;

class FormDoctrineQueryModifier extends AbstractFormDoctrineQueryModifier
{
	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithFontColorField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	/**
	 * @param piForm|Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return string|null
	 */
	protected function getValueForFontColorField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $doctrineRecord->fontColorAsHexValue();
	}

	protected function modifyQueryWithFontSizeField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	protected function getValueForFontSizeField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, $fieldDef->getDoctrineField(), FormPeer::FONT_SIZE_DEFAULT_ALT);
	}

	protected function modifyQueryWithFontFamilyField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	protected function getValueForFontFamilyField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, $fieldDef->getDoctrineField(), FormPeer::FONT_FAMILY_DEFAULT_VALUE_ALT);
	}

	protected function modifyQueryWithCheckboxAlignmentField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	protected function getValueForCheckboxAlignmentField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, $fieldDef->getDoctrineField(), FormPeer::CHECKBOX_ALIGN_DEFAULT_ALT);
	}

	protected function modifyQueryWithLabelAlignmentField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	protected function getValueForLabelAlignmentField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, $fieldDef->getDoctrineField(), FormPeer::LABEL_ALIGN_DEFAULT_ALT);
	}

	protected function modifyQueryWithRadioAlignmentField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection($fieldDef->getDoctrineField());
	}

	protected function getValueForRadioAlignmentField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, $fieldDef->getDoctrineField(), FormPeer::RADIO_ALIGN_DEFAULT_ALT);
	}

	protected function modifyQueryWithRequiredCharacterField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		$queryBuilderRoot->addSelection('required_char');
	}

	protected function getValueForRequiredCharacterField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		return $this->getValueOrDefault($doctrineRecord, 'required_char', FormPeer::REQUIRED_FIELD_CHAR_DEFAULT_ALT);
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param string $field
	 * @param int $default
	 * @return int
	 * @throws Doctrine_Exception
	 */
	private function getValueOrDefault(Doctrine_Record $doctrineRecord, string $field, int $default): int
	{
		if (!$doctrineRecord instanceof piForm) {
			throw new RuntimeException('Unexpected record returned. Expected piForm, received ' . get_class($doctrineRecord));
		}
		// The field stores null in the database to mean "default" however the API framework uses null to mean
		// absence of a value. Due to the discrepancy, we replace null with a value to indicate "default".
		$value = $doctrineRecord->get($field);
		if (empty($value)) {
			return $default;
		}
		return $value;
	}
}

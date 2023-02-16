<?php

namespace Api\Config\Objects\Export;

use Api\Actions\ActionArgumentsMarshaller as ExportProcedureArgumentsMarshaller;
use Api\Config\Objects\Export\Gen\Doctrine\AbstractExportDoctrineQueryModifier;
use Api\DataTypes\ConversionContext;
use Api\DataTypes\StringDataType;
use Api\Exceptions\ApiException;
use Api\Export\ExportManager;
use Api\Export\ProcedureDefinitionCatalog;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\Query\QueryContext;
use ApiErrorLibrary;
use apiTools;
use Doctrine_Query;
use Doctrine_Record;
use Doctrine_Record_Exception;
use Exception;
use Pardot\BackgroundQueues\Export\ScalingApiExportJobManager;
use RESTClient;
use stringTools;

class ExportDoctrineQueryModifier extends AbstractExportDoctrineQueryModifier
{
	private QueryContext $context;

	/**
	 * @param QueryContext $queryContext
	 * @param array $selections
	 * @return Doctrine_Query
	 */
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$this->context = $queryContext;
		return parent::createDoctrineQuery($queryContext, $selections);
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithProcedureField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot
			->addSelection('object')
			->addSelection("procedure_arguments")
			->addSelection("procedure_name");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return array
	 * @throws Doctrine_Record_Exception
	 * @throws Exception
	 */
	public function getValueForProcedureField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): array
	{
		$name = $doctrineRecord->get('procedure_name');
		$object = apiTools::getCamelCasedObjectNameFromId($doctrineRecord->get('object'));
		$arguments = $doctrineRecord->get('procedure_arguments');
		$version = $this->context->getVersion();
		// Likely reading a v4 export through v5 endpoint
		if (stringTools::contains($name, '_')) {
			$version = 3;
		}
		return (new ExportRepresentationHelper())->getProcedureData($version, $this->context->getAccountId(), $object, $arguments, $name);
	}

	/**
	 * @inheritDoc
	 */
	protected function modifyQueryWithFieldsField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot
			->addSelection('object')
			->addSelection('selected_fields');
	}

	/**
	 * @inheritDoc
	 */
	protected function getValueForFieldsField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		$objectName = apiTools::getCamelCasedObjectNameFromId($doctrineRecord->get('object'));
		$objectDefinition = ObjectDefinitionCatalog::getInstance()
			->findObjectDefinitionByObjectType(3, $this->context->getAccountId(), $objectName);
		$fields = json_decode($doctrineRecord->get('selected_fields'));
		return $this->getFieldsValue($objectDefinition, $fields, $this->context->getVersion());
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param array $fields
	 * @param int $version
	 * @return array
	 */
	public function getFieldsValue(ObjectDefinition $objectDefinition, array $fields, int $version): array
	{
		$unknownFields = [];
		$fieldNames = [];
		foreach ($fields as $fieldName) {
			$fieldsDefinition = $objectDefinition->getFieldByName($fieldName);
			if (!$fieldsDefinition) {
				$unknownFields[] = $fieldName;
			} else {
				$fieldNames[] = $fieldsDefinition->getNameVersioned($version);
			}
		}
		if (!empty($unknownFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_FIELDS,
				json_encode($unknownFields),
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}
		return $fieldNames;
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithIncludeByteOrderMarkField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection('parameters');
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return bool
	 * @throws Doctrine_Record_Exception
	 */
	protected function getValueForIncludeByteOrderMarkField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): bool
	{
		$parameters = $doctrineRecord->get('parameters');
		if (empty($parameters)) {
			return false;
		}

		$parametersArray = json_decode($parameters, true);
		if (empty($parametersArray[ExportManager::PARAM_INCLUDE_BYTE_ORDER_MARK])) {
			return false;
		}

		return (bool)$parametersArray[ExportManager::PARAM_INCLUDE_BYTE_ORDER_MARK];
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithMaxFileSizeBytesField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection('parameters');
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return int|null
	 * @throws Doctrine_Record_Exception
	 */
	protected function getValueForMaxFileSizeBytesField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): ?int
	{
		$parameters = $doctrineRecord->get('parameters');
		if (empty($parameters)) {
			return null;
		}

		$parametersArray = json_decode($parameters, true);
		if (empty($parametersArray[ScalingApiExportJobManager::PARAM_MAX_FILE_SIZE_BYTES])) {
			return null;
		}

		return (int)$parametersArray[ScalingApiExportJobManager::PARAM_MAX_FILE_SIZE_BYTES];
	}
}

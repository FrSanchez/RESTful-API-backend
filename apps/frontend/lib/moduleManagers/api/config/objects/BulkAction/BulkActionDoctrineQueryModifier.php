<?php

namespace Api\Config\Objects\BulkAction;

use Abilities;
use Api\Config\Objects\BulkAction\Gen\Doctrine\AbstractBulkActionDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use BulkActionApiConstants;
use Doctrine_Query;
use Doctrine_Record;
use Hostname;
use piApiBulkAction;
use piBackgroundQueueTable;
use sfContext;

class BulkActionDoctrineQueryModifier extends AbstractBulkActionDoctrineQueryModifier
{
	private int $version;
	private QueryContext $context;

	/**
	 * @param QueryContext $queryContext
	 * @param array $selections
	 * @return Doctrine_Query
	 */
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$this->version = $queryContext->getVersion();
		$this->context = $queryContext;
		$query = parent::createDoctrineQuery($queryContext, $selections);
		if (!$queryContext->getAccessContext()->getUserAbilities()->hasAbility(Abilities::ADMIN_BATCH_ACTIONS_VIEW)) {
			$query->addWhere('created_by = ?', $queryContext->getAccessContext()->getUserId());
		}
		return $query;
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 */
	protected function modifyQueryWithErrorsRefField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection("error_file_ref_id");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 */
	protected function getValueForErrorsRefField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piApiBulkAction $doctrineRecord */
		if(in_array($doctrineRecord->status, BulkActionApiConstants::ALIAS_STATUS_FINAL_ENUM) && $doctrineRecord->error_file_ref_id) {
			// Return the url for the downloadError
			return $this->getRef('errors', $doctrineRecord->id);
		}
		return null;
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithFilenameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection("parameters");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return string
	 */
	protected function getValueForFilenameField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): string
	{
		/** @var piApiBulkAction $doctrineRecord */
		return $this->getParamValue($doctrineRecord->parameters, BulkActionApiConstants::FIELD_FILENAME, null);
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithSendSystemEmailField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection("parameters");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return bool
	 */
	protected function getValueForSendSystemEmailField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): bool
	{
		/** @var piApiBulkAction $doctrineRecord */
		return $this->getParamValue($doctrineRecord->parameters, BulkActionApiConstants::PARAM_SEND_SYSTEM_EMAIL, false);
	}

	/**
	 * @param string $params
	 * @param string $paramName
	 * @param $default
	 * @return mixed
	 */
	protected function getParamValue(string $params, string $paramName, $default)
	{
		if (!empty($params)){
			$paramArray = unserialize($params);
			return ($paramArray[$paramName] ?? $default);
		} else {
			return $default;
		}
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithPercentCompleteField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection("background_queue_id");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return int
	 */
	protected function getValueForPercentCompleteField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef): int
	{
		// Final status returns 100% complete
		/** @var piApiBulkAction $doctrineRecord */
		if (in_array($doctrineRecord->status, BulkActionApiConstants::ALIAS_STATUS_FINAL_ENUM)) {
			return 100;
		}

		$percentComplete = 0;
		$totalBqjCount = piBackgroundQueueTable::getInstance()->countBackgroundQueueJobs($this->context->getAccountId(), $doctrineRecord->background_queue_id, null);
		if ($totalBqjCount) {
			$totalFinishedBqjCount = piBackgroundQueueTable::getInstance()->countBackgroundQueueJobs($this->context->getAccountId(), $doctrineRecord->background_queue_id, true);
			$percentComplete = floor($totalFinishedBqjCount*100/$totalBqjCount);
		}
		return (int)$percentComplete;
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithProcessedCountField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection("parameters");
	}

	/**
	 * @param Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForProcessedCountField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piApiBulkAction $doctrineRecord */
		return $this->getParamValue($doctrineRecord->parameters, BulkActionApiConstants::FIELD_NUMBER_RECORDS_PROCESSED, 0);
	}

	/**
	 * @param string $resource
	 * @param int $id
	 * @return string
	 */
	protected function getRef(string $resource, int $id)
	{
		$protocol = sfContext::getInstance()->getRequest()->isSecure() ? 'https' : 'http';
		return "{$protocol}://" . Hostname::getAppHostname() . "/api/v{$this->version}/bulk-actions/{$id}/${resource}";
	}
}

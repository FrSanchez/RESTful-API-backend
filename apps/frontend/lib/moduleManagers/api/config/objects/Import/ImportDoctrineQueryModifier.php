<?php
namespace Api\Config\Objects\Import;

use Abilities;
use Api\Config\Objects\Import\Gen\Doctrine\AbstractImportDoctrineQueryModifier;
use Doctrine_Record;
use Exception;
use Hostname;
use Pardot\Constants\ShardDb\Import\OriginConstants as ImportOriginConstants;
use Pardot\Constants\ShardDb\Import\StatusConstants as ImportStatusConstants;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;
use PardotLogger;
use piImport;
use sfContext;

class ImportDoctrineQueryModifier extends AbstractImportDoctrineQueryModifier
{
	private int $version;

	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$this->version = $queryContext->getVersion();
		$query = parent::createDoctrineQuery($queryContext, $selections);
		$query->whereIn('origin', [ImportOriginConstants::WIZARD, ImportOriginConstants::API_EXTERNAL]);
		if (!$queryContext->getAccessContext()->getUserAbilities()->hasAbility(Abilities::ADMIN_IMPORTS_VIEW)) {
			$query->addWhere('user_id =? ', $queryContext->getAccessContext()->getUserId());
		}
		return $query;
	}

	/**
	 * @param QueryBuilderNode $queryBuilderRoot
	 * @param FieldDefinition $fieldDef
	 * @return QueryBuilderNode
	 */
	protected function modifyQueryWithBatchesRefField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return $queryBuilderRoot->addSelection("status");
	}

	protected function getValueForBatchesRefField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piImport $doctrineRecord */
		if ($doctrineRecord->status == ImportStatusConstants::OPEN) {
			return $this->getRef('batches', $doctrineRecord->id);
		} else {
			return null;
		}
	}

	protected function modifyQueryWithErrorsRefField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef): QueryBuilderNode
	{
		return
			$queryBuilderRoot->addSelection("is_expired")
				->addSelection("num_failed");
	}

	protected function getValueForErrorsRefField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piImport $doctrineRecord */
		if (!$doctrineRecord->is_expired && $doctrineRecord->num_failed > 0) {
			return $this->getRef('errors', $doctrineRecord->id);
		}
		return null;
	}

	/**
	 * @param string $resource
	 * @param int $id
	 * @return string
	 */
	private function getRef(string $resource, int $id)
	{
		$protocol = sfContext::getInstance()->getRequest()->isSecure() ? 'https' : 'http';
		return "{$protocol}://" . Hostname::getAppHostname() . "/api/v{$this->version}/imports/{$id}/${resource}";
	}
}

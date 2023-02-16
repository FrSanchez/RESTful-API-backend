<?php
namespace Api\Config\BulkDataProcessors;

use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\BulkDataProcessorRelationshipHelper;
use Api\Objects\Query\QueryContext;
use Api\Objects\RecordIdCollection;
use Api\Objects\RecordIdValueCollection;
use Api\Objects\Relationships\RelationshipSelection;
use Api\Objects\SystemColumnNames;
use Doctrine_Exception;
use piEmailTemplateTable;

class VisitorActivityEmailTemplateBulkDataProcessor implements BulkDataProcessor
{
	private array $recordsToLoad;
	private array $emailTemplateIdToEmailTemplate;
	private ObjectDefinition $referencedObjectDefinition;

	/**
	 * VisitorActivityEmailTemplateBulkDataProcessor Constructor.
	 */
	public function __construct()
	{
		$this->recordsToLoad = [];
		$this->emailTemplateIdToEmailTemplate = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param QueryBuilderNode $queryBuilderNode
	 */
	public function modifyPrimaryQueryBuilder(
		ObjectDefinition $objectDefinition,
		$selection,
		QueryBuilderNode $queryBuilderNode
	): void
	{
		if ($selection->getRelationshipName() === 'emailTemplate') {
			$queryBuilderNode
				->addSelection('email_id')
				->addSelection('piEmail', 'id')
				->addSelection('piEmail', 'email_message_id')
				->addSelection('piEmail', 'piEmailMessage', 'email_template_id');
		}
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 */
	public function checkAndAddRecordToLoadIfNeedsLoading(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array $dbArray
	): void {
		if (is_null($doctrineRecord)) {
			return;
		}

		$recordId = $this->getEmailTemplateIdFromDoctrineRecord($doctrineRecord);
		if (is_null($recordId)) {
			return;
		}

		if (!$this->containsLoadedRecordForEmailTemplate($recordId)) {
			$this->recordsToLoad[] = $recordId;
			$this->referencedObjectDefinition = $selection->getReferencedObjectDefinition();
		}
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param array $selections
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Exception
	 */
	public function fetchData(
		QueryContext $queryContext,
		ObjectDefinition $objectDefinition,
		array $selections,
		bool $allowReadReplica
	): void
	{
		// Check if there are records that need loading
		if (count($this->recordsToLoad) == 0) {
			return;
		}

		$recordSelections = array_values(BulkDataProcessorRelationshipHelper::getSelectionsForObjectDefinition(
			$selections,
			$objectDefinition,
			$this->referencedObjectDefinition,
		));

		$recordIdToRecordCollection = BulkDataProcessorRelationshipHelper::getAssetDetails(
			$queryContext,
			$recordSelections,
			$this->recordsToLoad,
			$this->referencedObjectDefinition
		);

		foreach ($this->recordsToLoad as $emailTemplateId) {
			$emailTemplateRepresentation = null;
			if (array_key_exists($emailTemplateId, $recordIdToRecordCollection)) {
				$emailTemplateRepresentation = $recordIdToRecordCollection[$emailTemplateId];
			}
			$this->emailTemplateIdToEmailTemplate[$emailTemplateId] = $emailTemplateRepresentation;
		}
		$this->recordsToLoad = [];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition|RelationshipSelection $selection
	 * @param ImmutableDoctrineRecord|null $doctrineRecord
	 * @param array $dbArray
	 * @param int $apiVersion
	 * @return bool
	 */
	public function modifyRecord(
		ObjectDefinition $objectDefinition,
		$selection,
		?ImmutableDoctrineRecord $doctrineRecord,
		array &$dbArray,
		int $apiVersion
	): bool
	{
		if (is_null($doctrineRecord)) {
			return false;
		}

		$currentRecordId = $this->getEmailTemplateIdFromDoctrineRecord($doctrineRecord);
		if (is_null($currentRecordId)) {
			$dbArray[$selection->getRelationshipName()] = null;
			return false;
		}

		if (!$this->containsLoadedRecordForEmailTemplate($currentRecordId)) {
			return true;
		}

		$dbArray[$selection->getRelationshipName()] = $this->emailTemplateIdToEmailTemplate[$currentRecordId];
		return false;
	}

	/**
	 * @param int $recordId
	 * @return bool
	 */
	private function containsLoadedRecordForEmailTemplate(int $recordId): bool
	{
		return array_key_exists($recordId, $this->emailTemplateIdToEmailTemplate);
	}

	/**
	 * @param ImmutableDoctrineRecord $doctrineRecord
	 * @return string|null
	 */
	private function getEmailTemplateIdFromDoctrineRecord(ImmutableDoctrineRecord $doctrineRecord): ?string
	{
		$relation = $doctrineRecord->reference('piEmail');
		if (!is_null($relation)) {
			$relation = $relation->reference('piEmailMessage');
			if (!is_null($relation)) {
				return $relation->get(SystemColumnNames::EMAIL_TEMPLATE_ID);
			}
		}
		return null;
	}
}

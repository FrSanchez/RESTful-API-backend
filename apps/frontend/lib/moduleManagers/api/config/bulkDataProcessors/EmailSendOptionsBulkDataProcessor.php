<?php

namespace Api\Config\BulkDataProcessors;

use Api\Objects\Collections\RepresentationReferenceSelection;
use Api\Objects\Doctrine\ImmutableDoctrineRecord;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\ObjectDefinition;
use Api\Objects\Query\BulkDataProcessor;
use Api\Objects\Query\QueryContext;
use Api\Objects\Query\Selections\FieldRepresentationArraySelection;
use Api\Objects\SystemColumnNames;
use Api\Representations\RepresentationPropertyDefinition;
use Doctrine_Query_Exception;
use EmailConstants;
use piEmailSendOptions;
use piEmailSendOptionsTable;
use RuntimeException;

class EmailSendOptionsBulkDataProcessor implements BulkDataProcessor
{
	const ALLOWED_OBJECTS = [
		'ListEmail',
		'EmailTemplate',
	];
	const SENDER_OPTIONS = "senderOptions";
	const REPLY_TO_OPTIONS = "replyToOptions";

	private array $idsToLoad;
	private array $loadedEmailSendOptions = [];
	private ?piEmailSendOptionsTable $piEmailSendOptionsTable;

	public function __construct(?piEmailSendOptionsTable $piEmailSendOptionsTable = null)
	{
		$this->piEmailSendOptionsTable = $piEmailSendOptionsTable;
	}

	public function modifyPrimaryQueryBuilder(ObjectDefinition $objectDefinition, $selection, QueryBuilderNode $queryBuilderNode): void
	{
		$queryBuilderNode->addSelection(SystemColumnNames::ID);
	}

	public function checkAndAddRecordToLoadIfNeedsLoading(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array $dbArray): void
	{
		$objectType = $objectDefinition->getType();
		if (!in_array($objectType, self::ALLOWED_OBJECTS) ||
			!$selection instanceof FieldRepresentationArraySelection ||
			($selection->getRepresentationSelection()->getRepresentationName() != 'SendOptionRepresentation' && $selection->getRepresentationSelection()->getRepresentationName() != 'ReplyToOptionRepresentation')) {
			throw new RuntimeException('BulkProcessor supports: ' . implode(',', self::ALLOWED_OBJECTS));
		}

		if (is_null($doctrineRecord)) {
			return;
		}

		$this->idsToLoad[$doctrineRecord->get(SystemColumnNames::ID)] = null;
	}

	/**
	 * @param QueryContext $queryContext
	 * @param ObjectDefinition $objectDefinition
	 * @param FieldDefinition[] $selections
	 * @param bool $allowReadReplica
	 * @throws Doctrine_Query_Exception
	 */
	public function fetchData(QueryContext $queryContext, ObjectDefinition $objectDefinition, array $selections, bool $allowReadReplica): void
	{
		if (empty($this->idsToLoad)) {
			return;
		}

		$getSendFromData = false;
		$getReplyToAddress = false;
		foreach ($selections as $selection) {
			if (!$selection instanceof FieldRepresentationArraySelection) {
				continue;
			}
			$fieldDefinition = $selection->getFieldDefinition();
			if ($fieldDefinition->getName() == self::SENDER_OPTIONS) {
				$getSendFromData = true;
			}

			if ($fieldDefinition->getName() == self::REPLY_TO_OPTIONS) {
				$getReplyToAddress = true;
			}

			if ($getReplyToAddress && $getSendFromData) {
				break;
			}

		}

		if ($objectDefinition->getType() === 'EmailTemplate') {
			$fk = 'email_template_id';
		} else {
			$fk = 'email_id';
		}

		$piEmailSendOptions = $this->getPiEmailSendOptionsTable()->getSenderOptionData(
			$queryContext->getAccountId(),
			$fk,
			array_keys($this->idsToLoad),
			$getSendFromData,
			$getReplyToAddress
		);

		foreach ($piEmailSendOptions as $piEmailSendOption) {
			$this->loadedEmailSendOptions[(int) $piEmailSendOption->$fk] = $piEmailSendOption;
		}

		$this->loadedEmailSendOptions += $this->idsToLoad;
		$this->idsToLoad = [];
	}

	public function modifyRecord(ObjectDefinition $objectDefinition, $selection, ?ImmutableDoctrineRecord $doctrineRecord, array &$dbArray, int $apiVersion): bool
	{
		if (!$selection instanceof FieldRepresentationArraySelection ||
			($selection->getRepresentationSelection()->getRepresentationName() != 'SendOptionRepresentation' && $selection->getRepresentationSelection()->getRepresentationName() != 'ReplyToOptionRepresentation' )) {
			throw new RuntimeException('Unsupported selection specified');
		}
		if (is_null($doctrineRecord)) {
			return false;
		}

		$recordId = $doctrineRecord->get(SystemColumnNames::ID);

		if (is_null($recordId)) {
			return false;
		}

		// Request more data to be fetched
		if (!array_key_exists($recordId, $this->loadedEmailSendOptions)) {
			return true;
		}

		// no options for this record
		if (is_null($this->loadedEmailSendOptions[$recordId])) {
			$dbArray[$selection->getName()] = null;
			return false;
		}

		$selectedPropertyNames = $this->getSelectedPropertyNames($selection->getRepresentationSelection()->toArray());

		/** @var piEmailSendOptions $piEmailSendOption */
		$piEmailSendOption = $this->loadedEmailSendOptions[$recordId];
		if ($selection->getName() === self::SENDER_OPTIONS) {
			$dbArray[$selection->getName()] = $this->convertSendFromDataDatabaseValueToArray(
				$piEmailSendOption->send_from_data,
				$selectedPropertyNames
			);
		}

		if ($selection->getName() === self::REPLY_TO_OPTIONS) {
			$dbArray[$selection->getName()] = $this->convertReplyToAddressDatabaseValueToArray(
				$piEmailSendOption->reply_to_address,
				$selectedPropertyNames
			);
		}

		return false;
	}

	/**
	 * @return piEmailSendOptionsTable
	 */
	private function getPiEmailSendOptionsTable(): piEmailSendOptionsTable
	{
		if (!$this->piEmailSendOptionsTable) {
			$this->piEmailSendOptionsTable = piEmailSendOptionsTable::getInstance();
		}

		return $this->piEmailSendOptionsTable;
	}

	private function getSelectedPropertyNames(array $selections): array
	{
		$selectedFields = [];
		foreach ($selections as $selection) {
			if ($selection instanceof RepresentationPropertyDefinition) {
				$selectedFields[$selection->getName()] = true;
			} elseif ($selection instanceof RepresentationReferenceSelection) {
				$selectedFields[$selection->getPropertyName()] = true;
			} else {
				throw new RuntimeException("Unsupported selection type specified: " . get_class($selection));
			}
		}
		return array_values($selectedFields);
	}

	/**
	 * Converts the text string of JSON in the DB column into the DB arrays used by the API framework.
	 * @param string|null $dbValue
	 * @param string[] $selectedProperties
	 * @return array|null
	 */
	private function convertSendFromDataDatabaseValueToArray(?string $dbValue, array $selectedProperties): ?array
	{
		if (empty($dbValue)) {
			return null;
		}

		$options = json_decode($dbValue);
		$results = [];
		foreach ($options as $option) {
			$type = (is_array($option)) ? $option[0] : $option;
			$result = [];
			$this->setPropertyIfSelected($result, $selectedProperties, 'type', $type);
			switch ($type) {
				case EmailConstants::SENDER_GENERAL_USER:
					$this->setPropertyIfSelected($result, $selectedProperties, 'name', $option[1]);
					$this->setPropertyIfSelected($result, $selectedProperties, 'address', $option[2]);
					break;
				case EmailConstants::SENDER_SPECIFIC_USER:
					$this->setPropertyIfSelected($result, $selectedProperties, 'userId', $option[1]);
					break;
				case EmailConstants::SENDER_PROSPECT_CUSTOM_FIELD:
					$this->setPropertyIfSelected($result, $selectedProperties, 'prospectCustomFieldId', $option[1]);
					break;
				case EmailConstants::SENDER_ACCOUNT_CUSTOM_FIELD:
					$this->setPropertyIfSelected($result, $selectedProperties, 'accountCustomFieldId', $option[1]);
					break;
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Converts the text string of JSON in the DB column into the DB arrays used by the API framework.
	 * @param string|null $dbValue
	 * @param string[] $selectedProperties
	 * @return array|null $dbValue
	 */
	private function convertReplyToAddressDatabaseValueToArray(?string $dbValue, array $selectedProperties): ?array
	{
		if (empty($dbValue)) {
			return null;
		}

		$options = json_decode($dbValue);
		$results = [];
		foreach ($options as $option) {
			$type = (is_array($option)) ? $option[0] : $option;
			$result = [];

			$this->setPropertyIfSelected($result, $selectedProperties, 'type', $type);
			switch ($type) {
				case EmailConstants::REPLY_GENERAL_ADDRESS:
					$this->setPropertyIfSelected($result, $selectedProperties, 'address', $option[1]);
					break;
				case EmailConstants::REPLY_SPECIFIC_USER:
					$this->setPropertyIfSelected($result, $selectedProperties, 'userId', $option[1]);
					break;
				case EmailConstants::REPLY_PROSPECT_CUSTOM_FIELD:
					$this->setPropertyIfSelected($result, $selectedProperties, 'prospectCustomFieldId', $option[1]);
					break;
				case EmailConstants::REPLY_ACCOUNT_CUSTOM_FIELD:
					$this->setPropertyIfSelected($result, $selectedProperties, 'accountCustomFieldId', $option[1]);
					break;
			}

			$results[] = $result;
		}

		return $results;
	}

	/**
	 * Sets the property within the properties map if the property is found within the selected property names.
	 * @param array $properties
	 * @param string[] $selectedProperties
	 * @param string $property
	 * @param mixed $value
	 */
	private function setPropertyIfSelected(array &$properties, array $selectedProperties, string $property, $value): void
	{
		if (in_array($property, $selectedProperties)) {
			$properties[$property] = $value;
		}
	}
}

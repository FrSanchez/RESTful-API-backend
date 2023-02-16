<?php
namespace Api\Objects\Query;

use Api\Objects\RecordIdCollection;
use Api\Representations\Representation;
use Countable;

/**
 * Class ManyQueryResult
 * @package Api\Objects\Query
 */
class ManyQueryResult implements Countable
{
	private static $EMPTY;

	private $representations;
	private $additionalFieldsByIndex;
	private $redactedRecordIds;
	private $lastNonRedactedRecord;

	/**
	 * @param Representation[] $representations
	 * @param array[] $additionalFieldsByIndex
	 * @param RecordIdCollection $redactedRecordIds The IDs of the records that have been removed from the results, usually
	 * due to not having access to the record because of record permissions or abilities.
	 * @param mixed $lastNonRedactedRecord The last record before redacting the results. It is used to build a next page token
	 */
	public function __construct(
		array $representations,
		array $additionalFieldsByIndex,
		RecordIdCollection $redactedRecordIds,
		?array $lastNonRedactedRecord = null
	) {
		$this->representations = $representations;
		$this->additionalFieldsByIndex = $additionalFieldsByIndex;
		$this->redactedRecordIds = $redactedRecordIds;
		$this->lastNonRedactedRecord = $lastNonRedactedRecord;
	}

	/**
	 * @return array|null
	 */
	public function getLastNonRedactedRecord()
	{
		return $this->lastNonRedactedRecord;
	}

	/**
	 * @return Representation[]
	 */
	public function getRepresentations(): array
	{
		return $this->representations;
	}

	public function getRepresentation(int $index): Representation
	{
		return $this->representations[$index];
	}

	public function getAllAdditionalFields(): array
	{
		return $this->additionalFieldsByIndex;
	}

	public function getAdditionalFields(int $index): array
	{
		return $this->additionalFieldsByIndex[$index];
	}

	public function getAdditionalFieldByName(int $index, string $fieldName)
	{
		return $this->additionalFieldsByIndex[$index][$fieldName] ?? null;
	}

	public function count(): int
	{
		return count($this->representations);
	}

	public function isEmpty(): bool
	{
		return count($this->representations) == 0;
	}

	public function getRedactedRecordIds(): RecordIdCollection
	{
		return $this->redactedRecordIds;
	}

	public static function getEmptyManyQueryResult(): self
	{
		if (!self::$EMPTY) {
			self::$EMPTY = new ManyQueryResult([], [], new RecordIdCollection());
		}
		return self::$EMPTY;
	}

	/**
	 * Merges 2 different Query Results. Also, assumes $manyQueryResult2 was run after $manyQueryResult1 for redacted record.
	 * @param ManyQueryResult $firstQueryResult
	 * @param ManyQueryResult $secondQueryResult
	 * @return ManyQueryResult
	 */
	public static function mergeQueryResults(
		ManyQueryResult $firstQueryResult,
		ManyQueryResult $secondQueryResult
	): ManyQueryResult {
		$representations = array_merge($firstQueryResult->getRepresentations(), $secondQueryResult->getRepresentations());
		$additionalFieldsByIndex = array_merge($firstQueryResult->getAllAdditionalFields(), $secondQueryResult->getAllAdditionalFields());
		$lastNonRedactedRecord = $secondQueryResult->getLastNonRedactedRecord();

		$redactedRecordIds = $firstQueryResult->getRedactedRecordIds();
		foreach ($secondQueryResult->getRedactedRecordIds()->getObjectDefinitions() as $objectDefinition) {
			$redactedRecordIds->addRecordIds(
				$objectDefinition,
				$secondQueryResult->getRedactedRecordIds()->getRecordIdsByObjectDefinition($objectDefinition)
			);
		}

		return new ManyQueryResult($representations, $additionalFieldsByIndex, $redactedRecordIds, $lastNonRedactedRecord);
	}
}

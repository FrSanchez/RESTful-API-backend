<?php
namespace Api\Objects;

/**
 * Collection of multiple record IDs
 *
 * Class RecordCollection
 * @package Api\Objects\Access
 */
class RecordIdCollection
{
	/**
	 * Stores the records in an associative array where the key is the name of the object and the value is an associative
	 * array. The inner array must guarantee uniqueness so the key is always the ID and the value is always true.
	 *
	 * <code>
	 * $recordsByObjectName = [
	 *   'File' => [1 => true, 2 => true, 3 => true, 4 => true],
	 *   'User' => [10 => true, 11 => true, 12 => true]
	 * ];
	 * </code>
	 * @var array
	 */
	private $recordsByObjectName = [];

	/**
	 * Simple cache of object definitions by their name. This should be kept in sync with {@see $recordsByObjectName}
	 * @var array
	 */
	private $objectDefinitionsByName = [];

	public function addRecordId(ObjectDefinition $objectDefinition, int $id): void
	{
		$this->addRecordIds($objectDefinition, [$id]);
	}

	public function addRecordIds(ObjectDefinition $objectDefinition, array $ids): void
	{
		// make sure the object collections exist
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			$this->recordsByObjectName[$objectDefinition->getType()] = [];
			$this->objectDefinitionsByName[$objectDefinition->getType()] = $objectDefinition;
		}

		// check to see if the record is already in the collection
		foreach ($ids as $id) {
			if (isset($this->recordsByObjectName[$objectDefinition->getType()][$id])) {
				continue;
			}

			$this->recordsByObjectName[$objectDefinition->getType()][$id] = true;
		}
	}

	public function containsRecordId(ObjectDefinition $objectDefinition, int $id): bool
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			return false;
		}

		return isset($this->recordsByObjectName[$objectDefinition->getType()][$id]);
	}

	public function removeAllByObjectDefinition(ObjectDefinition $objectDefinition): void
	{
		if (isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			unset($this->recordsByObjectName[$objectDefinition->getType()]);
			unset($this->objectDefinitionsByName[$objectDefinition->getType()]);
		}
	}

	public function removeRecordId(ObjectDefinition $objectDefinition, int $id): void
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			return;
		}
		unset($this->recordsByObjectName[$objectDefinition->getType()][$id]);
		if (count($this->recordsByObjectName[$objectDefinition->getType()]) == 0) {
			unset($this->recordsByObjectName[$objectDefinition->getType()]);
		}
	}

	/**
	 * Gets an array of all unique object definitions that have been added to this collection.
	 * @return ObjectDefinition[]
	 */
	public function getObjectDefinitions(): array
	{
		return array_values($this->objectDefinitionsByName);
	}

	/**
	 * @param string $objectDefinitionName
	 * @return bool
	 */
	public function containsObjectDefinition(string $objectDefinitionName): bool
	{
		return array_key_exists($objectDefinitionName, $this->objectDefinitionsByName);
	}

	/**
	 * @param string $objectDefinitionName
	 * @return ObjectDefinition|null
	 */
	public function getObjectDefinitionByName(string $objectDefinitionName): ?ObjectDefinition
	{
		if (!$this->containsObjectDefinition($objectDefinitionName)) {
			return null;
		}

		return $this->objectDefinitionsByName[$objectDefinitionName];
	}

	/**
	 * Gets an array of all unique record IDs added to the collection for the given object definition. If no records
	 * exist for the object definition, an empty array is returned.
	 *
	 * @param ObjectDefinition $objectDefinition
	 * @return array
	 */
	public function getRecordIdsByObjectDefinition(ObjectDefinition $objectDefinition): array
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			return [];
		}

		return array_keys($this->recordsByObjectName[$objectDefinition->getType()]);
	}

	public function isEmpty(): bool
	{
		return count($this->objectDefinitionsByName) == 0;
	}

	public function removeAllObjectsAndRecords(): void
	{
		$this->recordsByObjectName = [];
		$this->objectDefinitionsByName = [];
	}
}

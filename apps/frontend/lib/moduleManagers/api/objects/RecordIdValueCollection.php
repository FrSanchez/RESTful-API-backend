<?php
namespace Api\Objects;

/**
 * Collection of multiple record IDs
 *
 * Class RecordCollection
 * @package Api\Objects\Access
 */
class RecordIdValueCollection
{
	/**
	 * Stores the records in an associative array where the key is the name of the object and the value is an associative
	 * array. The inner array must guarantee uniqueness so the key is always the ID and the value is ID the key is
	 * associated with.
	 *
	 * <code>
	 * $recordsByObjectName = [
	 *   'File' => [1 => 39, 2 => 1, 3 => 99, 4 => 101],
	 *   'User' => [10 => 62, 11 => 65, 12 => 1]
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

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $id
	 * @param mixed|null $value
	 */
	public function addRecordIdValue(ObjectDefinition $objectDefinition, int $id, $value = null): void
	{
		$this->addRecordIdValues($objectDefinition, [$id => $value]);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param array $idValues
	 */
	public function addRecordIdValues(ObjectDefinition $objectDefinition, array $idValues): void
	{
		// make sure the object collections exist
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			$this->recordsByObjectName[$objectDefinition->getType()] = [];
			$this->objectDefinitionsByName[$objectDefinition->getType()] = $objectDefinition;
		}

		// check to see if the record is already in the collection
		foreach ($idValues as $id => $value) {
			$this->recordsByObjectName[$objectDefinition->getType()][$id] = $value;
		}
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $id
	 * @return bool
	 */
	public function containsRecordId(ObjectDefinition $objectDefinition, int $id): bool
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			return false;
		}

		return array_key_exists($id, $this->recordsByObjectName[$objectDefinition->getType()]);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @return bool
	 */
	public function containsObjectDefinition(ObjectDefinition $objectDefinition): bool
	{
		return isset($this->recordsByObjectName[$objectDefinition->getType()]);
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 */
	public function removeAllByObjectDefinition(ObjectDefinition $objectDefinition): void
	{
		if (isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			unset($this->recordsByObjectName[$objectDefinition->getType()]);
			unset($this->objectDefinitionsByName[$objectDefinition->getType()]);
		}
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @param int $id
	 */
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
	 * @param ObjectDefinition $objectDefinition
	 * @param int $id
	 * @return mixed|null
	 */
	public function getRecordIdValueByObjectDefinition(ObjectDefinition $objectDefinition, int $id)
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()]) ||
			!array_key_exists($id, $this->recordsByObjectName[$objectDefinition->getType()])) {
			return null;
		}

		return $this->recordsByObjectName[$objectDefinition->getType()][$id];
	}

	/**
	 * @param ObjectDefinition $objectDefinition
	 * @return array|null
	 */
	public function getRecordIdValuesByObjectDefinition(ObjectDefinition $objectDefinition): ?array
	{
		if (!isset($this->recordsByObjectName[$objectDefinition->getType()])) {
			return null;
		}

		return $this->recordsByObjectName[$objectDefinition->getType()];
	}

	/**
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return count($this->objectDefinitionsByName) == 0;
	}
}

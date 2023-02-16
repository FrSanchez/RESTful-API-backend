<?php
namespace Api\Yaml;

use Countable;

class YamlArray implements Countable
{
	/** @var array $yamlArray */
	private $yamlArray;

	/** @var string[] */
	private $path;

	/**
	 * YamlArray constructor.
	 * @param array $yamlArray The YAML array after parsing
	 * @param string[] $path The path at which this array is found. If this is the root of the YAML, the path array should be empty.
	 * 					     Default is an empty array.
	 * @throws YamlException
	 */
	public function __construct(array $yamlArray, array $path = [])
	{
		if (!empty($yamlArray) && array_keys($yamlArray) !== range(0, count($yamlArray) - 1)) {
			throw new YamlException("Associative array received when a sequential array was expected.\npath: /" . join('/', $path));
		}

		$this->yamlArray = $yamlArray;
		$this->path = $path;
	}

	/**
	 * Asserts that the array is not empty.
	 * @param string $exceptionMessage
	 * @throws YamlException
	 */
	public function assertNotEmpty(string $exceptionMessage = null): void
	{
		if (empty($this->yamlArray)) {
			throw new YamlException($exceptionMessage ?: 'Array is empty');
		}
	}

	/**
	 * Gets the value at the specified index as string. If the index is not valid or is not a string, an exception is thrown.
	 * @param int $index The index to retrieve the value from.
	 * @return string The value at the specified index as a string.
	 * @throws YamlException When the index does not exist or the value is not a string.
	 */
	public function getValueAsString(int $index): string
	{
		$this->assertIndex($index);
		$value = $this->yamlArray[$index];
		if (!is_string($value)) {
			throw new YamlException("Index $index is not a string.\npath: " . $this->createPathStringWithIndex($index));
		}
		return (string)$value;
	}

	/**
	 * Gets the value at the specified index as a boolean. If the index is not valid or is not a boolean, an exception is thrown.
	 * @param int $index The index to retrieve the value from.
	 * @return bool The value at the specified index as a boolean.
	 * @throws YamlException When the index does not exist or the value is not a boolean.
	 */
	public function getValueAsBoolean(int $index): bool
	{
		$this->assertIndex($index);
		$value = $this->yamlArray[$index];
		if (!is_bool($value)) {
			throw new YamlException("Index $index is not a boolean.\npath: " . $this->createPathStringWithIndex($index));
		}
		return (bool)$value;
	}

	/**
	 * Gets the value at the specified index as a integer. If the index is not valid or is not a boolean, an exception is thrown.
	 * @param int $index The index to retrieve the value from.
	 * @return bool The value at the specified index as a integer.
	 * @throws YamlException When the index does not exist or the value is not an integer.
	 */
	public function getValueAsInteger(int $index): bool
	{
		$this->assertIndex($index);
		$value = $this->yamlArray[$index];
		if (!is_int($value)) {
			throw new YamlException("Index $index is not an integer.\npath: " . $this->createPathStringWithIndex($index));
		}
		return (int)$value;
	}

	/**
	 * Gets the value at the specified index as an object. If the index is not valid or is not a object, an exception is thrown.
	 * @param int $index The index to retrieve the value from.
	 * @return YamlObject The value at the specified index as an object.
	 * @throws YamlException When the index does not exist or the value is not an object.
	 */
	public function getValueAsObject(int $index): YamlObject
	{
		$this->assertIndex($index);

		$value = $this->yamlArray[$index];
		if (!is_array($value) || !$this->isAssociativeArray($value)) {
			throw new YamlException("Index $index is not an array or is not an associative array to create an object.\npath: " . $this->createPathStringWithIndex($index));
		}

		$newPath = $this->path;
		array_push($newPath, $index);
		return new YamlObject($value, $newPath);
	}

	/**
	 * @param int $index The index to retrieve the value from.
	 * @return YamlArray The value of the index as an array
	 * @throws YamlException
	 */
	public function getValueAsArray(int $index): YamlArray
	{
		$this->assertIndex($index);

		$value = $this->yamlArray[$index];
		if (!is_array($value) || $this->isAssociativeArray($value)) {
			throw new YamlException("Index $index is not an array or is an associative array instead of a sequential array.\npath: " . $this->createPathStringWithIndex($index));
		}

		$newPath = $this->path;
		array_push($newPath, $index);
		return new YamlArray($value, $newPath);
	}

	/**
	 * Gets the number of values in the array.
	 * @return int
	 */
	public function count(): int
	{
		return count($this->yamlArray);
	}

	/**
	 * Determines if the index is valid.
	 * @param int $index
	 * @return bool
	 */
	public function hasIndex(int $index): bool
	{
		return $index >= 0 && $index < $this->count();
	}

	/**
	 * Makes sure that the array has the specified index. If not, it throws an exception.
	 * @param int $index
	 * @throws YamlException
	 */
	private function assertIndex(int $index)
	{
		if (!$this->hasIndex($index)) {
			throw new YamlException("Index $index is not within array range.\npath: " . $this->createPathStringWithIndex($index));
		}
	}

	/**
	 * Creates a string from the path within this instance including the specified index on the end.
	 *
	 * The output should look similar to these:
	 * <li>/0
	 * <li>/parent/child/1
	 *
	 * @param int $index The index to be appended to the end of the path.
	 * @return string The string generated from the path.
	 */
	private function createPathStringWithIndex(int $index): string
	{
		if (count($this->path) > 0) {
			return '/' . join('/', $this->path) . '/' . $index;
		} else {
			return '/' . $index;
		}
	}

	/**
	 * Determines if the given array is an associative array.
	 * @param array $arr
	 * @return bool
	 */
	private function isAssociativeArray(array $arr): bool
	{
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
}

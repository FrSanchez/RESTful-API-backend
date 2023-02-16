<?php
namespace Api\Yaml;

class YamlObject
{
	/** @var array $yamlContent */
	private $yamlContent;

	/** @var string[] */
	private $path;

	/**
	 * YamlObject constructor.
	 * @param array $yamlContent The YAML contents after parsing
	 * @param string[] $path The path at which this object is found. If this is the root object, the path array should be empty.
	 * 					     Default is an empty array.
	 * @throws YamlException
	 */
	public function __construct(array $yamlContent, array $path = [])
	{
		if (!$this->isAssociativeArray($yamlContent)) {
			throw new YamlException("Parsed yaml content is not an associative array.\npath: /" . join('/', $path));
		}

		$this->yamlContent = $yamlContent;
		$this->path = $path;
	}

	/**
	 * @param array $arr
	 * @return bool
	 */
	private function isAssociativeArray(array $arr): bool
	{
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	/**
	 * @param $name
	 * @return bool
	 */
	public function hasProperty($name): bool
	{
		return isset($this->yamlContent[$name]);
	}

	/**
	 * @return array
	 */
	public function getPropertyNames(): array
	{
		return array_keys($this->yamlContent);
	}

	/**
	 * @param $name
	 * @return mixed
	 */
	private function getProperty($name)
	{
		return $this->yamlContent[$name];
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string|null $exceptionMessage The message to throw when the property is not found. When not specified or
	 *      								null, a default message is added.
	 * @param string $exceptionClass The exception to throw when the property is not found. Default is YamlException.
	 * @return string The value of the property if the property exists and is a string.
	 * @throws YamlException
	 */
	public function getRequiredPropertyAsString(string $name, ?string $exceptionMessage = null, string $exceptionClass = YamlException::class): string
	{
		if (!$this->hasProperty($name) || !is_string($this->getProperty($name))) {
			throw new $exceptionClass(($exceptionMessage ?: "Required property {$name} does not exist or is not a string.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (string)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message thrown when the property cannot be converted to a string.
	 * @return string|null The value of the property if the property exists and is a string. If the property does not
	 *                     exist, then a null is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsString(string $name, string $exceptionMessage = null): ?string
	{
		if (!$this->hasProperty($name)) {
			return null;
		}

		if (!is_string($this->getProperty($name))) {
			throw new YamlException(($exceptionMessage ?: "Property {$name} does not have a value of type string.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (string)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $default The value to use as a default.
	 * @param string $exceptionMessage The message thrown when the property cannot be converted to a string.
	 * @return string The value of the property if the property exists and is a string. If the property does not
	 *                exist, then the value from default argument is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsStringWithDefault(string $name, string $default, string $exceptionMessage = null): string
	{
		$value = $this->getPropertyAsString($name, $exceptionMessage);
		if (is_null($value)) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string|null $exceptionMessage The message to throw when the property is not found. When not specified or
	 *      								null, a default message is added.
	 * @param string $exceptionClass The exception to throw when the property is not found. Default is YamlException.
	 * @return int The value of the property if the property exists and is an integer.
	 * @throws YamlException
	 */
	public function getRequiredPropertyAsInteger(string $name, ?string $exceptionMessage = null, string $exceptionClass = YamlException::class): int
	{
		if (!$this->hasProperty($name) || !is_int($this->getProperty($name))) {
			throw new $exceptionClass(($exceptionMessage ?: "Property {$name} does not have a value or is not an integer.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (int)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message to throw when the property is not found. When not specified, a default message is added.
	 * @return int|null The value of the property if the property exists and is an integer. If the property does not
	 *                  exist then a null is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsInteger(string $name, string $exceptionMessage = null): ?int
	{
		if (!$this->hasProperty($name)) {
			return null;
		}

		if (!is_int($this->getProperty($name))) {
			throw new YamlException(($exceptionMessage ?: "Property {$name} does not have a value of type int.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (int)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to assert the value of. This property must be found.
	 * @param int|null $minValue The minimum value (exclusive) allowed for this property. If null, then no lower bound is checked.
	 * @param int|null $maxValue The maximum value (exclusive) allowed for this property. If null, then no upper bound is checked.
	 * @throws YamlException Thrown when the property is not found, the value of the property is not an integer or the
	 *                       value of the property is not within the min and max values.
	 */
	public function assertIntegerPropertyValue(string $name, int $minValue, ?int $maxValue): void
	{
		$intValue = $this->getRequiredPropertyAsInteger($name);
		if (!is_null($minValue) && $intValue < $minValue) {
			throw new YamlException("{$intValue} is not greater than or equal to {$minValue}" . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		if (!is_null($maxValue) && $intValue > $maxValue) {
			throw new YamlException("{$intValue} is not less than or equal to {$maxValue}" . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}
	}

	/**
	 *
	 * @param string $name The name of the property to retrieve the value from.
	 * @param int|null $default The default value to use when the property is not specified.
	 * @param string|null $exceptionMessage The message to throw when the property is not found. When not specified or
	 *      								null, a default message is added.
	 * @return int The value of the property if the property exists and is an integer. If the property does not
	 *                  exist then the default value is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsIntegerWithDefault(string $name, int $default, string $exceptionMessage = null): int
	{
		$value = $this->getPropertyAsInteger($name, $exceptionMessage);
		if (is_null($value)) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string|null $exceptionMessage The message to throw when the property is not found or when not a boolean.
	 *      								 When not specified or null, a default message is added.
	 * @param string $exceptionClass The exception to throw when the property is not found or is not a boolean. Default is YamlException.
	 * @return bool The value of the property if the property exists and is a boolean.
	 * @throws YamlException
	 */
	public function getRequiredPropertyAsBoolean(string $name, ?string $exceptionMessage = null, string $exceptionClass = YamlException::class): bool
	{
		if (!$this->hasProperty($name) || !is_bool($this->getProperty($name))) {
			throw new $exceptionClass(($exceptionMessage ?: "Property {$name} does not have a value of type bool.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (bool)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message to throw when the property is not a boolean. When not specified or
	 *                                 null, a default message is added.
	 * @return bool|null The value of the property if the property exists and is an boolean. If the property does not
	 *                  exist then a null is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsBoolean(string $name, string $exceptionMessage = null): ?bool
	{
		if (!$this->hasProperty($name)) {
			return null;
		}

		if (!is_bool($this->getProperty($name))) {
			throw new YamlException(($exceptionMessage ?: "Property {$name} does not have a value of type bool.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		return (bool)$this->getProperty($name);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param bool $default The default value to return when the property is not specified.
	 * @param string $exceptionMessage The message to throw when the property is not a boolean. When not specified or
	 *                                 null, a default message is added.
	 * @return bool The value of the property if the property exists and is a boolean. If the property does not
	 *              exist then the default value is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsBooleanWithDefault(string $name, bool $default, string $exceptionMessage = null): bool
	{
		$value = $this->getPropertyAsBoolean($name, $exceptionMessage);
		if (is_null($value)) {
			$value = $default;
		}

		return $value;
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message to throw when the property is not found or is not an object. When not
	 *                                 specified or null, a default message is added.
	 * @param string $exceptionClass The exception to throw when the property is not found or is not an object. Default is YamlException.
	 * @return YamlObject The value of the property if the property exists and is an object.
	 * @throws YamlException
	 */
	public function getRequiredPropertyAsObject(string $name, string $exceptionMessage = null, string $exceptionClass = YamlException::class): YamlObject
	{
		if (!$this->hasProperty($name) || !is_array($this->getProperty($name)) || !$this->isAssociativeArray($this->getProperty($name))) {
			throw new $exceptionClass(($exceptionMessage ?: "Property {$name} is not an array or is not an associative array to create an object.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		$newPath = $this->path;
		array_push($newPath, $name);
		return new YamlObject($this->getProperty($name), $newPath);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message to throw when the property is not an object. When not specified or
	 *                                 null, a default message is added.
	 * @return YamlObject|null The value of the property if the property exists and is an object. If the property does not
	 *                  exist then a null is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsObject(string $name, string $exceptionMessage = null): ?YamlObject
	{
		if (!$this->hasProperty($name)) {
			return null;
		}

		if (!is_array($this->getProperty($name)) || !$this->isAssociativeArray($this->getProperty($name))) {
			throw new YamlException(($exceptionMessage ?: "Property {$name} is not an array or is not an associative array to create an object.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		$newPath = $this->path;
		array_push($newPath, $name);
		return new YamlObject($this->getProperty($name), $newPath);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string|null $exceptionMessage The message to throw when the property is not found or not an array. When
	 *                                      not specified or null, a default message is added.
	 * @param string $exceptionClass The exception to throw when the property is not found or is not an array. Default is YamlException.
	 * @return YamlArray The value of the property if the property exists and is an array.
	 * @throws YamlException
	 */
	public function getRequiredPropertyAsArray(string $name, ?string $exceptionMessage = null, string $exceptionClass = YamlException::class): YamlArray
	{
		if (!$this->hasProperty($name) || !is_array($this->getProperty($name)) || $this->isAssociativeArray($this->getProperty($name))) {
			throw new $exceptionClass(($exceptionMessage ?: "Property {$name} is not an array or is an associative array instead of a sequential array.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		$newPath = $this->path;
		array_push($newPath, $name);
		return new YamlArray($this->getProperty($name), $newPath);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param string $exceptionMessage The message to throw when the property is not an array. When not specified or
	 *                                 null, a default message is added.
	 * @return YamlArray|null The value of the property if the property exists and is an array. If the property does not
	 *                        exist then a null is returned.
	 * @throws YamlException
	 */
	public function getPropertyAsArray(string $name, string $exceptionMessage = null): ?YamlArray
	{
		if (!$this->hasProperty($name)) {
			return null;
		}

		if (!is_array($this->getProperty($name)) || $this->isAssociativeArray($this->getProperty($name))) {
			throw new YamlException(($exceptionMessage ?: "Property {$name} is not an array or is an associative array instead of a sequential array.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}

		$newPath = $this->path;
		array_push($newPath, $name);
		return new YamlArray($this->getProperty($name), $newPath);
	}

	/**
	 * @param string $name The name of the property to retrieve the value from.
	 * @param array $default The default value to return when the property is not specified.
	 * @param string $exceptionMessage The message to throw when the property is not an array. When not specified or
	 *                                 null, a default message is added.
	 * @return YamlArray The value of the property if the property exists and is an array. If the property does not
	 *                   exist then the default value is returned as YamlArray.
	 * @throws YamlException
	 */
	public function getPropertyAsArrayWithDefault(string $name, array $default, string $exceptionMessage = null): YamlArray
	{
		$value = $this->getPropertyAsArray($name, $exceptionMessage);
		if (is_null($value)) {
			$newPath = $this->path;
			array_push($newPath, $name);
			$value = new YamlArray($default, $newPath);
		}

		return $value;
	}

	/**
	 * Throws an exception when the property is not specified within the object. Usually one of the other "getRequired"
	 * methods is used instead of this method, which checks requiredness and retrieves the value with the same method.
	 * @param string $name The name of the property.
	 * @param string|null $exceptionMessage The message to throw when the property is not specified. When not specified or
	 * 										null, a default message is used.
	 * @throws YamlException
	 */
	public function assertRequiredProperty(string $name, string $exceptionMessage = null, string $exceptionClass = YamlException::class): void
	{
		if (!$this->hasProperty($name)) {
			throw new $exceptionClass(($exceptionMessage ?: "Required property {$name} is not specified.") . "\npath: " . $this->createPathStringWithLeafProperty($name));
		}
	}

	/**
	 * Determines if the value of the specified property is a string. If the property is not specified, null or any data type
	 * besides a string, a false value is returned.
	 * @param string $name The name of the property.
	 * @return bool True when the value is a string.
	 */
	public function isStringPropertyValue(string $name): bool
	{
		return $this->hasProperty($name) && !is_null($this->getProperty($name)) && is_string($this->getProperty($name));
	}

	private function createPathStringWithLeafProperty(string $property): string
	{
		if (count($this->path) > 0) {
			return '/' . join('/', $this->path) . '/' . $property;
		} else {
			return '/' . $property;
		}
	}
}

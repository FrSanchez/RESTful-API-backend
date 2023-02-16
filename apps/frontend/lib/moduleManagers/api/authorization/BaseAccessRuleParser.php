<?php
namespace Api\Authorization;

use Api\Yaml\YamlException;
use Api\Yaml\YamlObject;

abstract class BaseAccessRuleParser
{
	const ALL_OPERATOR = '$allOf';
	const ANY_OPERATOR = '$anyOf';

	/**
	 * @param YamlObject $yamlObject
	 * @param string $property
	 * @return array[string, YamlArray]
	 * @throws YamlException
	 */
	protected function getOperatorValues(YamlObject $yamlObject, string $property): array
	{
		$keys = $yamlObject->getPropertyNames();
		if (count($keys) != 1) {
			throw new YamlException("Invalid or unknown operator values for {$property}. Must be " . self::ALL_OPERATOR . " or " . self::ANY_OPERATOR . ".");
		}

		$operator = $keys[0];
		if (array_search($operator, [self::ALL_OPERATOR, self::ANY_OPERATOR]) === false) {
			throw new YamlException("Invalid or unknown operator value for {$property}: {$keys[0]}. Must be " . self::ALL_OPERATOR . " or " . self::ANY_OPERATOR . ".");
		}

		return [$operator, $yamlObject->getPropertyAsArray($operator)];
	}
}

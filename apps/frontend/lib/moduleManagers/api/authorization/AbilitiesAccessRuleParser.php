<?php
namespace Api\Authorization;

use Abilities;
use AbilitiesAccessRule;
use RuntimeException;
use Api\Yaml\YamlArray;
use Api\Yaml\YamlException;
use Api\Yaml\YamlObject;

class AbilitiesAccessRuleParser extends BaseAccessRuleParser
{
	/**
	 * @param YamlObject $yamlObject
	 * @param string $propertyName
	 * @return AbilitiesAccessRule
	 * @throws YamlException
	 */
	public function parseFromRequiredYamlProperty(YamlObject $yamlObject, string $propertyName = 'abilities'): AbilitiesAccessRule
	{
		$yamlObject->assertRequiredProperty($propertyName);
		if ($yamlObject->isStringPropertyValue($propertyName)) {
			// handle the case when a single ability is specified
			$abilityName = $yamlObject->getPropertyAsString($propertyName);
			$ability = $this->parseAbilityConstantFromName($abilityName, $propertyName);
			return AbilitiesAccessRule::allOf($ability);
		} else {
			$abilities = $yamlObject->getPropertyAsObject($propertyName);
			return $this->parseAbilities($abilities, $propertyName);
		}
	}

	public function parseFromYamlProperty(YamlObject $yamlObject, string $propertyName = 'abilities'): AbilitiesAccessRule
	{
		if ($yamlObject->hasProperty($propertyName)) {
			return $this->parseFromRequiredYamlProperty($yamlObject, $propertyName);
		}

		return AbilitiesAccessRule::deny();
	}

	private function parseAbilities(YamlObject $abilities, string $propertyName): AbilitiesAccessRule
	{
		/** @var YamlArray $requiredAbilities */
		list($operator, $requiredAbilities) = $this->getOperatorValues($abilities, $propertyName);

		$abilityValues = [];
		for ($index = 0; $index < $requiredAbilities->count(); $index++) {
			$abilityName = $requiredAbilities->getValueAsString($index);
			$abilityValues[] = $this->parseAbilityConstantFromName($abilityName, $propertyName);
		}

		if ($operator == self::ALL_OPERATOR) {
			$accessRule = AbilitiesAccessRule::allOf($abilityValues);
		} elseif ($operator == self::ANY_OPERATOR) {
			$accessRule = AbilitiesAccessRule::anyOf($abilityValues);
		} else {
			throw new RuntimeException("Invalid or unknown ability operator value in property {$propertyName}: {$operator}");
		}

		return $accessRule;
	}

	/**
	 * @param string $abilityName
	 * @param string $propertyName
	 * @return mixed
	 */
	private function parseAbilityConstantFromName(string $abilityName, string $propertyName)
	{
		$ability = constant(Abilities::class . "::{$abilityName}");

		if ($ability === null) {
			throw new RuntimeException("Ability {$abilityName} in {$propertyName} could not be found");
		}

		return $ability;
	}
}

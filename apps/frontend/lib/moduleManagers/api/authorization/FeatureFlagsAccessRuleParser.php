<?php
namespace Api\Authorization;

use Api\Yaml\YamlArray;
use Api\Yaml\YamlException;
use Api\Yaml\YamlObject;
use FeatureFlagAccessRule;
use ReflectionClass;
use ReflectionException;
use stringTools;

class FeatureFlagsAccessRuleParser extends BaseAccessRuleParser
{
	/**
	 * Feature flags can be specified like:
	 *
	 * $allOf: [ "feature.foo_the_bar", ... ]
	 *
	 * or:
	 *
	 * $anyOf: [ "feature.foo_the_bar", ... ]
	 *
	 * Values in the array must come from AccountSettingsConstants.class.php.
	 *
	 * @param YamlObject $yamlObject
	 * @param string $propertyName - optional parameter for specifying property name containing feature flag(s)
	 * @param bool $allowMultiple - specifies where property allows multiple feature flags to be specified
	 * @return FeatureFlagAccessRule
	 * @throws YamlException
	 */
	public function parseFromYamlProperty(YamlObject $yamlObject, string $propertyName = 'featureFlags', bool $allowMultiple = true): FeatureFlagAccessRule
	{
		if (!$yamlObject->hasProperty($propertyName)) {
			return FeatureFlagAccessRule::allowAnyone();
		}

		// fetch all of the feature constants that are defined in AccountSettingsManager
		$accessSettingLowerValueToValueMap = self::fetchAccountSettingConstants();

		$yamlObject->assertRequiredProperty($propertyName);
		if ($yamlObject->isStringPropertyValue($propertyName)) {
			// handle the case when a single feature flag is specified
			$featureFlagName = $yamlObject->getPropertyAsString($propertyName);
			$ability = $this->parseFeatureFlagNameToConstant($featureFlagName, $accessSettingLowerValueToValueMap);
			return FeatureFlagAccessRule::allOf($ability);
		} else if ($allowMultiple) {
			$featureFlags = $yamlObject->getPropertyAsObject($propertyName);
			return $this->parseFeatureFlagAccessRuleCollection($featureFlags, $propertyName, $accessSettingLowerValueToValueMap);
		} else {
			throw new YamlException("Malformed {$propertyName} property value.  This property expects a single feature flag string");
		}
	}

	private function parseFeatureFlagAccessRuleCollection(YamlObject $featureFlags, string $propertyName, array $accessSettingLowerValueToValueMap): FeatureFlagAccessRule
	{
		/** @var YamlArray $requiredFeatureFlags */
		list($operator, $requiredFeatureFlags) = $this->getOperatorValues($featureFlags, $propertyName);

		// verify that each of the specified feature flags are found in AccountSettingsManager
		$verifiedRequiredFeatureFlags = [];
		for ($index = 0; $index < $requiredFeatureFlags->count(); $index++) {
			$requiredFeatureFlag = $requiredFeatureFlags->getValueAsString($index);
			if (substr($requiredFeatureFlag, 0, 8) == 'feature.' &&
				array_key_exists(strtolower(substr($requiredFeatureFlag, 8)), $accessSettingLowerValueToValueMap)) {
				$verifiedRequiredFeatureFlags[] = $accessSettingLowerValueToValueMap[strtolower(substr($requiredFeatureFlag, 8))];
			} else {
				if (array_key_exists(strtolower($requiredFeatureFlag), $accessSettingLowerValueToValueMap)) {
					throw new YamlException("Feature flag {$requiredFeatureFlag} must begin with prefix 'feature.'");
				}

				throw new YamlException("Feature flag {$requiredFeatureFlag} could not be found");
			}
		}

		if ($operator == self::ALL_OPERATOR) {
			$accessRule = FeatureFlagAccessRule::allOf($verifiedRequiredFeatureFlags);
		} elseif ($operator == self::ANY_OPERATOR) {
			$accessRule = FeatureFlagAccessRule::anyOf($verifiedRequiredFeatureFlags);
		} else {
			throw new YamlException("Invalid or unknown operator value for featureFlags: {$operator}");
		}

		return $accessRule;
	}

	private function parseFeatureFlagNameToConstant(string $featureFlagName, array $accessSettingLowerValueToValueMap)
	{
		if (substr($featureFlagName, 0, 8) == 'feature.' &&
			array_key_exists(strtolower(substr($featureFlagName, 8)), $accessSettingLowerValueToValueMap)) {
			return $accessSettingLowerValueToValueMap[strtolower(substr($featureFlagName, 8))];
		}

		if (array_key_exists(strtolower($featureFlagName), $accessSettingLowerValueToValueMap)) {
			throw new YamlException("Feature flag {$featureFlagName} must begin with prefix 'feature.'");
		}

		throw new YamlException("Feature flag {$featureFlagName} could not be found");
	}

	/**
	 * @return array
	 */
	private static function fetchAccountSettingConstants(): array
	{
		try {
			$reflectionClass = new ReflectionClass(\AccountSettingsConstants::class);
			$constantMap = [];
			foreach ($reflectionClass->getConstants() as $constantName => $constantValue) {
				if (stringTools::startsWith($constantName, 'FEATURE_')) {
					$constantMap[strtolower($constantValue)] = $constantValue;
				}
			}
			return $constantMap;
		} catch (ReflectionException $exc) {
			throw new YamlException('Failed to load settings from AccountSettingsConstants using reflection', $exc);
		}
	}
}

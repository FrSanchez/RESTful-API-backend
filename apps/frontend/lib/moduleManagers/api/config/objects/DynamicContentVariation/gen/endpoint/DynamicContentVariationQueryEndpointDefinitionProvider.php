<?php
namespace Api\Config\Objects\DynamicContentVariation\Gen\Endpoint;

use Api\Endpoints\EndpointParameterDefinition;
use Api\Endpoints\ObjectQueryEndpointDefinitionProvider;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class DynamicContentVariationQueryEndpointDefinitionProvider extends ObjectQueryEndpointDefinitionProvider
{
	protected function getAdditionalEndpointParameters(): array
	{
		$additionalParameters = [];

		$additionalParameters[] = new EndpointParameterDefinition('id', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('dynamicContentId', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('dynamicContentIdGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('dynamicContentIdGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('dynamicContentIdLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('dynamicContentIdLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());

		return array_merge(
			parent::getAdditionalEndpointParameters(),
			$additionalParameters
		);
	}
}
<?php
namespace Api\Config\Objects\LandingPage\Gen\Endpoint;

use Api\Endpoints\EndpointParameterDefinition;
use Api\Endpoints\ObjectQueryEndpointDefinitionProvider;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class LandingPageQueryEndpointDefinitionProvider extends ObjectQueryEndpointDefinitionProvider
{
	protected function getAdditionalEndpointParameters(): array
	{
		$additionalParameters = [];

		$additionalParameters[] = new EndpointParameterDefinition('id', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('createdAt', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('createdAtAfter', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('createdAtAfterOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('createdAtBefore', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('createdAtBeforeOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('updatedAt', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('updatedAtAfter', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('updatedAtAfterOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('updatedAtBefore', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('updatedAtBeforeOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());

		return array_merge(
			parent::getAdditionalEndpointParameters(),
			$additionalParameters
		);
	}
}

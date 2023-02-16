<?php
namespace Api\Config\Objects\VisitorPageView\Gen\Endpoint;

use Api\Endpoints\EndpointParameterDefinition;
use Api\Endpoints\ObjectQueryEndpointDefinitionProvider;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class VisitorPageViewQueryEndpointDefinitionProvider extends ObjectQueryEndpointDefinitionProvider
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
		$additionalParameters[] = new EndpointParameterDefinition('visitorId', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitorIdGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitorIdGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitorIdLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitorIdLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitId', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitIdGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitIdGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitIdLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('visitIdLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());

		return array_merge(
			parent::getAdditionalEndpointParameters(),
			$additionalParameters
		);
	}
}

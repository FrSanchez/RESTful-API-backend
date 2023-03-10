<?php
namespace Api\Config\Objects\Email\Gen\Endpoint;

use Api\Endpoints\EndpointParameterDefinition;
use Api\Endpoints\ObjectQueryEndpointDefinitionProvider;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class EmailQueryEndpointDefinitionProvider extends ObjectQueryEndpointDefinitionProvider
{
	protected function getAdditionalEndpointParameters(): array
	{
		$additionalParameters = [];

		$additionalParameters[] = new EndpointParameterDefinition('id', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('idLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('sentAt', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('sentAtAfter', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('sentAtAfterOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('sentAtBefore', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('sentAtBeforeOrEqualTo', \Api\DataTypes\DateTimeDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('prospectId', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('prospectIdGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('prospectIdGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('prospectIdLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('prospectIdLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('listEmailId', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('listEmailIdGreaterThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('listEmailIdGreaterThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('listEmailIdLessThan', \Api\DataTypes\IntegerDataType::getInstance());
		$additionalParameters[] = new EndpointParameterDefinition('listEmailIdLessThanOrEqualTo', \Api\DataTypes\IntegerDataType::getInstance());

		return array_merge(
			parent::getAdditionalEndpointParameters(),
			$additionalParameters
		);
	}
}

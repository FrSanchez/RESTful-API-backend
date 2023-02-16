<?php
namespace Api\Config\Objects\LifecycleStageProspect\Gen\Endpoint;

use Api\Endpoints\EndpointDefinitionProvider;
use Api\Endpoints\EndpointInputDefinition;
use Api\Endpoints\EndpointTreeNode;
use Api\Endpoints\ObjectDeleteEndpointDefinitionProvider;
use Api\Endpoints\ObjectReadEndpointDefinitionProvider;
use Api\Endpoints\ObjectUpdatePartialEndpointDefinitionProvider;
use Api\Objects\ObjectDefinitionCatalog;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class LifecycleStageProspectObjectIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;


	public function __construct(ObjectDefinitionCatalog $objectDefinitionCatalog)
	{
		$this->objectDefinitionCatalog = $objectDefinitionCatalog;
	}

	public function doesChildWithPathPartExist(string $pathPart): bool
	{
		// No endpoint is found past "objects/{objectName}/{id}"
		return false;
	}

	public function getChildWithPathPart(string $pathPart): EndpointTreeNode
	{
		// No endpoint is found past "objects/{objectName}/{id}"
		throw new RuntimeException("No child matches path part $pathPart");
	}

	public function hasEndpointDefinitionForMethod(string $method): bool
	{
		return false;
	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{
		throw new RuntimeException("Unsupported method: " . $method);
	}



}
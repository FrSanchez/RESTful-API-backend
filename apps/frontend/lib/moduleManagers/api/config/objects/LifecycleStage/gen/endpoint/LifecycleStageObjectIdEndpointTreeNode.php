<?php
namespace Api\Config\Objects\LifecycleStage\Gen\Endpoint;

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
class LifecycleStageObjectIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;

	private ?ObjectReadEndpointDefinitionProvider $readEndpointDefinitionProvider = null;

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
		if ($method === 'GET') {
			return true;
		}
		return false;
	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{
		if ($method === 'GET') {
			return $this->getReadEndpointDefinitionProvider();
		}
		throw new RuntimeException("Unsupported method: " . $method);
	}

	private function getReadEndpointDefinitionProvider(): ObjectReadEndpointDefinitionProvider
	{
		if (is_null($this->readEndpointDefinitionProvider)) {
			$this->readEndpointDefinitionProvider = new ObjectReadEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'LifecycleStage',
				'LifecycleStageRepresentation',
				\Api\Gen\Representations\LifecycleStageRepresentation::class,
true			);
		}
		return $this->readEndpointDefinitionProvider;
	}


}

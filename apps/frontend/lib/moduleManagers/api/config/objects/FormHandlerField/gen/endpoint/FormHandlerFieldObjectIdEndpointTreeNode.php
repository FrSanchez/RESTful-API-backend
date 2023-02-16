<?php
namespace Api\Config\Objects\FormHandlerField\Gen\Endpoint;

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
class FormHandlerFieldObjectIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;

	private ?ObjectReadEndpointDefinitionProvider $readEndpointDefinitionProvider = null;
	private ?ObjectUpdatePartialEndpointDefinitionProvider $updatePartialEndpointDefinitionProvider = null;
	private ?ObjectDeleteEndpointDefinitionProvider $deleteEndpointDefinitionProvider = null;

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
		if ($method === 'PATCH') {
			return true;
		}
		if ($method === 'DELETE') {
			return true;
		}
		return false;
	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{
		if ($method === 'GET') {
			return $this->getReadEndpointDefinitionProvider();
		}
		if ($method === 'PATCH') {
			return $this->getUpdatePartialEndpointDefinitionProvider();
		}
		if ($method === 'DELETE') {
			return $this->getDeleteEndpointDefinitionProvider();
		}
		throw new RuntimeException("Unsupported method: " . $method);
	}

	private function getReadEndpointDefinitionProvider(): ObjectReadEndpointDefinitionProvider
	{
		if (is_null($this->readEndpointDefinitionProvider)) {
			$this->readEndpointDefinitionProvider = new ObjectReadEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'FormHandlerField',
				'FormHandlerFieldRepresentation',
				\Api\Gen\Representations\FormHandlerFieldRepresentation::class,
false			);
		}
		return $this->readEndpointDefinitionProvider;
	}

	private function getUpdatePartialEndpointDefinitionProvider(): ObjectUpdatePartialEndpointDefinitionProvider
	{
		if (is_null($this->updatePartialEndpointDefinitionProvider)) {
			$this->updatePartialEndpointDefinitionProvider = new ObjectUpdatePartialEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'FormHandlerField',
				EndpointInputDefinition::createInputDefinitionWithRepresentationAndBinary(
					'FormHandlerFieldRepresentation',
					\Api\Gen\Representations\FormHandlerFieldRepresentation::class,
false				),
				'FormHandlerFieldRepresentation',
				\Api\Gen\Representations\FormHandlerFieldRepresentation::class
			);
		}
		return $this->updatePartialEndpointDefinitionProvider;
	}

	private function getDeleteEndpointDefinitionProvider(): ObjectDeleteEndpointDefinitionProvider
	{
		if (is_null($this->deleteEndpointDefinitionProvider)) {
			$this->deleteEndpointDefinitionProvider = new ObjectDeleteEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
'FormHandlerField'			);
		}
		return $this->deleteEndpointDefinitionProvider;
	}
}

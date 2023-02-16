<?php
namespace Api\Config\Objects\EmailTemplate\Gen\Endpoint;

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
class EmailTemplateObjectIdEndpointTreeNode implements EndpointTreeNode
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
				'EmailTemplate',
				'EmailTemplateRepresentation',
				\Api\Gen\Representations\EmailTemplateRepresentation::class,
true			);
		}
		return $this->readEndpointDefinitionProvider;
	}

	private function getUpdatePartialEndpointDefinitionProvider(): ObjectUpdatePartialEndpointDefinitionProvider
	{
		if (is_null($this->updatePartialEndpointDefinitionProvider)) {
			$this->updatePartialEndpointDefinitionProvider = new ObjectUpdatePartialEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'EmailTemplate',
				EndpointInputDefinition::createInputDefinitionWithRepresentationAndBinary(
					'EmailTemplateRepresentation',
					\Api\Gen\Representations\EmailTemplateRepresentation::class,
false				),
				'EmailTemplateRepresentation',
				\Api\Gen\Representations\EmailTemplateRepresentation::class
			);
		}
		return $this->updatePartialEndpointDefinitionProvider;
	}

	private function getDeleteEndpointDefinitionProvider(): ObjectDeleteEndpointDefinitionProvider
	{
		if (is_null($this->deleteEndpointDefinitionProvider)) {
			$this->deleteEndpointDefinitionProvider = new ObjectDeleteEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
'EmailTemplate'			);
		}
		return $this->deleteEndpointDefinitionProvider;
	}
}
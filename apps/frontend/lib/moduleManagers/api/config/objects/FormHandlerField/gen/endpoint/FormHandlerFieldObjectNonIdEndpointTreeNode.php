<?php
namespace Api\Config\Objects\FormHandlerField\Gen\Endpoint;

use Api\Endpoints\EndpointDefinitionProvider;
use Api\Endpoints\EndpointInputDefinition;
use Api\Endpoints\EndpointTreeNode;

use Api\Endpoints\ObjectCreateEndpointDefinitionProvider;
use Api\Endpoints\ObjectQueryEndpointDefinitionProvider;
use Api\Objects\ObjectDefinitionCatalog;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class FormHandlerFieldObjectNonIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;

	private ?ObjectCreateEndpointDefinitionProvider $createEndpointDefinitionProvider = null;

	private ?ObjectQueryEndpointDefinitionProvider $queryEndpointDefinitionProvider = null;

	private ?EndpointTreeNode $idEndpointTreeNode = null;

	public function __construct(ObjectDefinitionCatalog $objectDefinitionCatalog)
	{
		$this->objectDefinitionCatalog = $objectDefinitionCatalog;
	}

	public function doesChildWithPathPartExist(string $pathPart): bool
	{


		// handle {id}
		if (is_numeric($pathPart)) {
			return true;
		}


		return false;

	}

	public function getChildWithPathPart(string $pathPart): EndpointTreeNode
	{

		if (is_numeric($pathPart)) {
			return $this->getIdEndpointTreeNode();
		}
		throw new RuntimeException("No child matches path part $pathPart");

	}

	public function hasEndpointDefinitionForMethod(string $method): bool
	{

		if ($method === 'GET') {
			return true;
		}
		if ($method === 'POST') {
			return true;
		}
		return false;

	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{

		if ($method === 'GET') {
			return $this->getQueryEndpointDefinitionProvider();
		}
		if ($method === 'POST') {
			return $this->getCreateEndpointDefinitionProvider();
		}
		throw new RuntimeException("Unsupported method: " . $method);

	}


	private function getIdEndpointTreeNode(): EndpointTreeNode
	{
		if (is_null($this->idEndpointTreeNode)) {
			$this->idEndpointTreeNode = new \Api\Config\Objects\FormHandlerField\Gen\Endpoint\FormHandlerFieldObjectIdEndpointTreeNode($this->objectDefinitionCatalog);
		}
		return $this->idEndpointTreeNode;
	}


	private function getCreateEndpointDefinitionProvider(): ObjectCreateEndpointDefinitionProvider
	{
		if (is_null($this->createEndpointDefinitionProvider)) {
			$this->createEndpointDefinitionProvider = new ObjectCreateEndpointDefinitionProvider(
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
		return $this->createEndpointDefinitionProvider;
	}

	private function getQueryEndpointDefinitionProvider(): ObjectQueryEndpointDefinitionProvider
	{
		if (is_null($this->queryEndpointDefinitionProvider)) {
			$this->queryEndpointDefinitionProvider = new \Api\Config\Objects\FormHandlerField\Gen\Endpoint\FormHandlerFieldQueryEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'FormHandlerField',
				'FormHandlerFieldQueryResultCollectionRepresentation',
				\Api\Gen\Representations\FormHandlerFieldQueryResultCollectionRepresentation::class,
false
			);
		}

		return $this->queryEndpointDefinitionProvider;
	}



}

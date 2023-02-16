<?php
namespace Api\Config\Objects\Form\Gen\Endpoint;

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
class FormObjectNonIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;


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
		return false;

	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{

		if ($method === 'GET') {
			return $this->getQueryEndpointDefinitionProvider();
		}
		throw new RuntimeException("Unsupported method: " . $method);

	}


	private function getIdEndpointTreeNode(): EndpointTreeNode
	{
		if (is_null($this->idEndpointTreeNode)) {
			$this->idEndpointTreeNode = new \Api\Config\Objects\Form\Gen\Endpoint\FormObjectIdEndpointTreeNode($this->objectDefinitionCatalog);
		}
		return $this->idEndpointTreeNode;
	}



	private function getQueryEndpointDefinitionProvider(): ObjectQueryEndpointDefinitionProvider
	{
		if (is_null($this->queryEndpointDefinitionProvider)) {
			$this->queryEndpointDefinitionProvider = new \Api\Config\Objects\Form\Gen\Endpoint\FormQueryEndpointDefinitionProvider(
				$this->objectDefinitionCatalog,
				'Form',
				'FormQueryResultCollectionRepresentation',
				\Api\Gen\Representations\FormQueryResultCollectionRepresentation::class,
true
			);
		}

		return $this->queryEndpointDefinitionProvider;
	}



}

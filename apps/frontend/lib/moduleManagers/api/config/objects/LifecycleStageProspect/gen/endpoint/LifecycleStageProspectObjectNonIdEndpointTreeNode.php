<?php
namespace Api\Config\Objects\LifecycleStageProspect\Gen\Endpoint;

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
class LifecycleStageProspectObjectNonIdEndpointTreeNode implements EndpointTreeNode
{
	private ObjectDefinitionCatalog $objectDefinitionCatalog;



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

		return false;

	}

	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{

		throw new RuntimeException("Unsupported method: " . $method);

	}


	private function getIdEndpointTreeNode(): EndpointTreeNode
	{
		if (is_null($this->idEndpointTreeNode)) {
			$this->idEndpointTreeNode = new \Api\Config\Objects\LifecycleStageProspect\Gen\Endpoint\LifecycleStageProspectObjectIdEndpointTreeNode($this->objectDefinitionCatalog);
		}
		return $this->idEndpointTreeNode;
	}





}

<?php
namespace Api\Config\Objects\Prospect\Gen\Endpoint;

use Api\Endpoints\EndpointDefinitionProvider;
use Api\Endpoints\EndpointTreeNode;
use Api\Endpoints\EndpointTreeNodeImpl;
use Api\Endpoints\ObjectActionExecuteEndpointDefinitionProvider;
use RuntimeException;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
class ProspectObjectActionsEndpointTreeNode implements EndpointTreeNode
{
	/**
	 * Given a path part, checks to see if a child corresponding to the path exists.
	 * @param string $pathPart
	 * @return bool True if a child with the path part exists.
	 */
	public function doesChildWithPathPartExist(string $pathPart): bool
	{
		if (strcasecmp($pathPart, 'undelete') === 0) {
			return true;
		}
		if (strcasecmp($pathPart, 'upsertLatestByEmail') === 0) {
			return true;
		}
		return false;
	}

	/**
	 * Gets the child node with the given path part. If the path part does not exist, a RuntimeException is thrown.
	 * @param string $pathPart
	 * @return EndpointTreeNode
	 */
	public function getChildWithPathPart(string $pathPart): EndpointTreeNode
	{
		if (strcasecmp($pathPart, 'undelete') === 0) {
			return $this->createEndpointTreeNodeForUndeleteAction();
		}
		if (strcasecmp($pathPart, 'upsertLatestByEmail') === 0) {
			return $this->createEndpointTreeNodeForUpsertLatestByEmailAction();
		}
		throw new RuntimeException("No child matches path part $pathPart");
	}

	/**
	 * Give an HTTP method, checks to see if the current path supports the given method.
	 * @param string $method
	 * @return bool True if the HTTP method is supported.
	 */
	public function hasEndpointDefinitionForMethod(string $method): bool
	{
		return false;
	}

	/**
	 * Gets the endpoint definition provider for the given HTTP method. If the method is not supported, a
	 * RuntimeException is thrown.
	 * @param string $method
	 * @return EndpointDefinitionProvider
	 */
	public function getEndpointDefinitionForMethod(string $method): EndpointDefinitionProvider
	{
		throw new RuntimeException("Unsupported method: " . $method);
	}

	private function createEndpointTreeNodeForUndeleteAction(): EndpointTreeNode
	{
		$postEndpointDefinitionProvider = new ObjectActionExecuteEndpointDefinitionProvider(
			null,
			'Prospect',
			'undelete',
			'ProspectUndeleteObjectActionInputRepresentation',
			'\Api\Gen\Representations\ProspectUndeleteObjectActionInputRepresentation',
			'ProspectRepresentation',
			'\Api\Gen\Representations\ProspectRepresentation',
		);
		return new EndpointTreeNodeImpl(
			[
				'POST' => $postEndpointDefinitionProvider
			],
			[]
		);
	}
	private function createEndpointTreeNodeForUpsertLatestByEmailAction(): EndpointTreeNode
	{
		$postEndpointDefinitionProvider = new ObjectActionExecuteEndpointDefinitionProvider(
			null,
			'Prospect',
			'upsertLatestByEmail',
			'ProspectUpsertLatestByEmailObjectActionInputRepresentation',
			'\Api\Gen\Representations\ProspectUpsertLatestByEmailObjectActionInputRepresentation',
			'ProspectRepresentation',
			'\Api\Gen\Representations\ProspectRepresentation',
		);
		return new EndpointTreeNodeImpl(
			[
				'POST' => $postEndpointDefinitionProvider
			],
			[]
		);
	}
}
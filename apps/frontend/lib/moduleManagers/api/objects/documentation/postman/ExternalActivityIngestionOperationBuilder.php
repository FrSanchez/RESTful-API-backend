<?php
namespace Api\Objects\Postman;

use RESTClient;
use stdClass;

class ExternalActivityIngestionOperationBuilder extends OperationBuilder
{
	/**
	 * @return Operation
	 */
	private function generateExternalActivityIngestion(): Operation
	{
		$operation = OperationFactory::create(Operation::INGESTION, RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$url->setPath(["api", "v{$this->version}", $this->objectDefinition->getUrlObjectName()]);

		$input = new stdClass();
		$input->extension = "extension API name";
		$input->type = "activity type API name";
		$input->email = "prospect@email.com";
		$input->value = "any value";
		json_encode($input, JSON_PRETTY_PRINT);
		$request->setBody($this->generateRawBody(json_encode($input, JSON_PRETTY_PRINT)));
		return $operation;
	}

	public function build(): ?Operation
	{
		return $this->generateExternalActivityIngestion();
	}
}

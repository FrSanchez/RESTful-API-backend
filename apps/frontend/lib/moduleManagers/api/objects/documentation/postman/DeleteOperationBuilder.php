<?php

namespace Api\Objects\Postman;

use RESTClient;

class DeleteOperationBuilder extends OperationBuilder
{
	protected function generateDeleteOperation(): Operation
	{
		$operation = OperationFactory::create(Operation::DELETE, RESTClient::METHOD_DELETE);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addPathToUrl($url, true);
		$this->addIdVariable($url);
		return $operation;
	}

	public function build(): ?Operation
	{
		return $this->generateDeleteOperation();
	}
}

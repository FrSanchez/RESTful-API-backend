<?php

namespace Api\Objects\Postman;

use ReflectionException;
use RESTClient;

class CreateOperationBuilder extends UpdateOperationBuilder
{
	/**
	 * @return Operation
	 * @throws ReflectionException
	 */
	protected function generateCreateOperation(): Operation
	{
		$operation = OperationFactory::create(Operation::CREATE, RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addPathToUrl($url);
		$this->addFieldsToUrlQuery($url, true);
		$request->setBody($this->generateCreateAndUpdateBody(false, $this->objectDefinition->hasBinaryAttachment()));
		return $operation;
	}

	/**
	 * @return Operation|null
	 * @throws ReflectionException
	 */
	public function build(): ?Operation
	{
		return $this->generateCreateOperation();
	}
}

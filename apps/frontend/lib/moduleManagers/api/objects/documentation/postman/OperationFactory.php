<?php

namespace Api\Objects\Postman;

class OperationFactory
{
	/**
	 * @param $name
	 * @param $method
	 * @return Operation
	 */
	public static function create($name, $method): Operation
	{
		$operation = new Operation($name);
		$request = $operation->getRequest();
		$request->setMethod($method);

		$url = $request->getUrl();
		$url->setProtocol("https");
		$url->setHost(["{{domain}}"]);
		return $operation;
	}
}

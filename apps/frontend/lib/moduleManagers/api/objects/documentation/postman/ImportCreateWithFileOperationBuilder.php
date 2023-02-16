<?php

namespace Api\Objects\Postman;

use Api\DataTypes\DataType;
use ReflectionException;
use RESTClient;

class ImportCreateWithFileOperationBuilder extends CreateOperationBuilder
{
	/**
	 * @return Operation
	 * @throws ReflectionException
	 */
	protected function generateCreateOperation(): Operation
	{
		$operation = OperationFactory::create('Create with File', RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addPathToUrl($url);
		$this->addFieldsToUrlQuery($url, true);
		$request->setBody($this->generateCreateAndUpdateBody(false, true));
		return $operation;
	}

	public function getValue(string $name, $type, DataType $dt)
	{
		if ($name == 'status') {
			return 'ready';
		}
		return parent::getValue($name, $type, $dt);
	}
}

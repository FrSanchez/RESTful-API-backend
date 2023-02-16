<?php
namespace Api\Objects\Postman;

use RESTClient;

class ImportBatchOperationBuilder extends OperationBuilder
{
	/**
	 * @return Operation
	 */
	protected function generateBatchOperation(): Operation
	{
		$operation = OperationFactory::create('Batch', RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$path = $this->calculatePathForUrl(true);
		$path[] = 'batches';
		$url->setPath($path);
		$request->setBody($this->generateBatchBody());
		return $operation;
	}

	/**
	 * @return Operation|null
	 */
	public function build(): ?Operation
	{
		return $this->generateBatchOperation();
	}

	protected function generateBatchBody(): Body
	{
		$bodyMode = "formdata";

		$body = new Body();
		$body->setMode($bodyMode);
		$file = new Entry("file", "file", null, null, null, []);
		$body->setFormdata([$file]);

		return $body;
	}
}

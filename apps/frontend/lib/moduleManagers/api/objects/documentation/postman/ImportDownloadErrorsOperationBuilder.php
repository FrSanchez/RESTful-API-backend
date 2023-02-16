<?php
namespace Api\Objects\Postman;

use RESTClient;

class ImportDownloadErrorsOperationBuilder extends OperationBuilder
{
	/**
	 * @return Operation
	 */
	protected function generateDownloadErrorsOperation(): Operation
	{
		$operation = OperationFactory::create('Download Errors', RESTClient::METHOD_GET);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$path = $this->calculatePathForUrl(true);
		$path[] = 'batches';
		$url->setPath($path);
		return $operation;
	}

	public function build(): ?Operation
	{
		return $this->generateDownloadErrorsOperation();
	}
}

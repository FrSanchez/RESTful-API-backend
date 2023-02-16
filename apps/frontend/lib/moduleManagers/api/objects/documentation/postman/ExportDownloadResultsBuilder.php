<?php
namespace Api\Objects\Postman;

use RESTClient;

class ExportDownloadResultsBuilder extends OperationBuilder
{
	protected function generateDownloadResults(): Operation
	{
		$operation = OperationFactory::create('Download Results', RESTClient::METHOD_GET);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$path = $this->calculatePathForUrl(true);
		$path[] = 'results';
		$path[] = ':fileId';
		$url->setPath($path);
		return $operation;
	}

	/**
	 * @return Operation|null
	 */
	public function build(): ?Operation
	{
		return $this->generateDownloadResults();
	}
}

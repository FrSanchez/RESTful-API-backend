<?php

/**
 * Class SalesforceRestApiForPardotTenantHelper
 *
 * This helper class is for performing HTTP POST, GET and DELETE operations on PardotTenant table in Salesforce
 */
class SalesforceRestApiForPardotTenantHelper
{
	use SalesforceConnectionManagerAwareTrait;
	protected $salesforce_url = null;
	protected $jwClient = null;
	protected $guzzleClient = null;
	protected $orgId = null;
	private $endPoint = null;
	const END_POINT = "/services/data/v45.0/tooling/sobjects/PardotTenant/";
	const V_45 = "v45.0";
	public function __construct(
		$salesforce_url,
		$orgId,
		$client = null,
		$accessToken = null,
		$instanceUrl = null,
		$sfApiVersion = self::V_45,
		$uriPath = self::END_POINT
	) {
		Preconditions::checkNonEmpty($orgId && strlen($orgId) == 18);
		$this->orgId = $orgId;
		$this->salesforce_url = $salesforce_url;
		$this->endPoint = is_null($uriPath) ? self::END_POINT : $uriPath;
		$this->jwClient = new SalesforceRestApiWithJwtHelperWithoutAccountId(
			$this->salesforce_url,
			$this->orgId,
			$client,
			$accessToken,
			$instanceUrl,
			$sfApiVersion);

		$this->guzzleClient = sfContext::getInstance()->getContainer()->get("http.client.proxied");
	}

	public function doGet($resource, $options = [])
	{
		$options = arrayTools::arrayMergeRecursiveDistinct($options, [
			'headers' => $this->jwClient->getAuthHeaderWithContentType()
		]);

		try {
			return $this->guzzleClient->get(
				$this->jwClient->getInstanceUrl() . $this->endPoint . $resource,
				$options
			);
		} catch (Exception $ex) {
			$this->jwClient->error("Failed to retrieve Salesforce payload: " . $ex->getMessage());
			if ($ex->getCode() === 404)
			{
				return $ex;
			}

			throw $ex;
		}
	}

	public function doDelete($resource, $options = [])
	{
		$options = arrayTools::arrayMergeRecursiveDistinct($options, [
			'headers' => $this->jwClient->getAuthHeaderWithContentType()
		]);

		try {
			return $this->guzzleClient->delete(
				$this->jwClient->getInstanceUrl(). $this->endPoint . $resource,
				$options
			);
		} catch (Exception $ex) {
			$this->jwClient->error("Failed to retrieve Salesforce payload: " . $ex->getMessage());
			if ($ex->getCode() === 404)
			{
				return $ex;
			}

			throw $ex;
		}
	}

	public function doPost($options, $postParams)
	{
		$options = arrayTools::arrayMergeRecursiveDistinct($options, [
			'headers' => $this->jwClient->getAuthHeaderWithContentType(),
			'body' => json_encode($postParams),
		]);

		try {
			return $this->guzzleClient->post(
				$this->jwClient->getInstanceUrl() . $this->endPoint,
				$options
			);
		} catch (Exception $ex) {
			$this->jwClient->error("Failed to retrieve Salesforce payload: " . $ex->getMessage());
			throw $ex;
		}
	}
}

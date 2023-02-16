<?php
namespace Api\Framework;

use Api\DataTypes\ConversionContext;
use Api\Endpoints\EndpointDefinition;
use Api\Exceptions\ApiException;
use ApiErrorLibrary;
use RESTClient;
use DateTime;
use Exception;

class ApiRequestPageToken
{
	public const VALID_TOKEN_KEYS = [
		"expireTime",
		"resumeValue",
		"filters",
		"orderBy",
		"limit",
		"page",
		"recCount",
		"deleted"
	];

	private string $encodedNextPageToken;
	private ?array $decodedPageToken;
	private EndpointDefinition $endpointDefinition;
	private ConversionContext $conversionContext;

	/**
	 * ApiRequestPageToken constructor.
	 * @param string $encodedNextPageToken
	 * @param EndpointDefinition $endpointDefinition
	 * @param ConversionContext $conversionContext
	 * @throws Exception
	 */
	public function __construct(
		string $encodedNextPageToken,
		EndpointDefinition $endpointDefinition,
		ConversionContext $conversionContext
	) {
		$this->encodedNextPageToken = $encodedNextPageToken;
		$this->endpointDefinition = $endpointDefinition;
		$this->conversionContext = $conversionContext;
		$this->decodedPageToken = $this->decodePageToken();

		$this->validatePageToken();
	}

	/**
	 * @return array|null
	 */
	private function decodePageToken(): ?array
	{
		$base64decode = base64_decode($this->encodedNextPageToken);
		return $base64decode ? json_decode($base64decode, JSON_OBJECT_AS_ARRAY) : null;
	}

	/**
	 * @throws Exception
	 */
	private function validatePageToken(): void
	{
		if (!$this->decodedPageToken) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_PAGE_TOKEN_INVALID, null, RESTClient::HTTP_BAD_REQUEST);
		}

		foreach (self::VALID_TOKEN_KEYS as $key) {
			if (! array_key_exists($key, $this->decodedPageToken)) {
				throw new ApiException(ApiErrorLibrary::API_ERROR_PAGE_TOKEN_INVALID, null, RESTClient::HTTP_BAD_REQUEST);
			}
		}

		if (new DateTime() > new DateTime($this->decodedPageToken["expireTime"])) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_PAGE_TOKEN_EXPIRED, null, RESTClient::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * @return DateTime
	 * @throws Exception
	 */
	public function getExpireTime(): DateTime
	{
		return new DateTime($this->decodedPageToken["expireTime"]);
	}

	/**
	 * @return string
	 */
	public function getOrderBy(): string
	{
		return $this->decodedPageToken["orderBy"];
	}

	/**
	 * @return ApiParameter[]
	 */
	public function getFilters(): array
	{
		$filters = $this->decodedPageToken["filters"];

		$parameters = [];
		foreach ($filters as $filterName => $value) {
			$lowerCaseFilterName = strtolower($filterName);

			foreach ($this->endpointDefinition->getParameterNames() as $parameterName) {
				$lowerCaseParamName = strtolower($parameterName);

				if (strcmp($lowerCaseFilterName, $lowerCaseParamName) === 0) {
					$parameterDefinition = $this->endpointDefinition->getParameterByName($parameterName);

					// Convert the value specified to the server value
					$serverValue = $parameterDefinition->getDataType()
						->convertParameterValueToServerValue($value, $this->conversionContext);

					$parameters[$parameterName] = new ApiParameter($parameterDefinition->getDataType(), $value, $serverValue);
				}
			}
		}

		return $parameters;
	}

	/**
	 * @return int
	 */
	public function getLimit(): int
	{
		return $this->decodedPageToken["limit"];
	}

	/**
	 * @return ResumeValue
	 */
	public function getResumeValue(): ResumeValue
	{
		return new ResumeValue($this->decodedPageToken["resumeValue"]);
	}

	/**
	 * @return int
	 */
	public function getPage(): int
	{
		return $this->decodedPageToken["page"];
	}

	/**
	 * @return int
	 */
	public function getRecCount(): int
	{
		return $this->decodedPageToken["recCount"];
	}

	public function getDeleted()
	{
		$decodedToken = $this->decodePageToken();
		return $decodedToken["deleted"];
	}
}

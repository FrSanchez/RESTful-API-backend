<?php
namespace Api\Deserialization;

use Api\Exceptions\ApiException;

use \ApiErrorLibrary;
use Exception;
use \generalTools;
use PardotLogger;
use piWebRequest;
use piWebRequestException;
use \RESTClient;

class JsonDeserializer
{
	/**
	 * @param piWebRequest $request
	 * @param string $paramName
	 * @param bool $allowEmpty
	 * @return array
	 * @throws Exception
	 */
	public function deserializeFromMultipartParamToArray($request, string $paramName, $allowEmpty = false): ?array
	{
		// Input needs to be valid JSON
		if (!\apiTools::isMultipartFormMimeType($request)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNSUPPORTED_CONTENT_TYPE,
				"Content-Type must be '" . \apiTools::MIME_TYPE_MULTIPART_FORM_DATA . "'",
				RESTClient::HTTP_BAD_REQUEST);
		}

		try {
			$inputValue = $request->getMultipartFormValue($paramName);
		} catch (piWebRequestException $e){
			PardotLogger::getInstance()->error($e->getMessage());
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN, piWebRequestException::getUserSafeMessage($e->getCode()), RESTClient::HTTP_BAD_REQUEST);
		}

		if (!isset($inputValue) || strlen(trim($inputValue)) == 0) {
			if ($allowEmpty) {
				return null;
			}
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_REQUEST_BODY,
				$paramName . ' is empty or missing',
				RESTClient::HTTP_BAD_REQUEST);
		}

		return $this->deserializeFromStringToArray($inputValue);
	}

	/**
	 * @param piWebRequest $request
	 * @param bool $allowEmpty
	 * @return array
	 */
	public function deserializeFromRequestBodyToArray(piWebRequest $request, bool $allowEmpty = false): array
	{
		// content type needs to be JSON
		if (!\apiTools::isJsonMimeType($request)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_UNSUPPORTED_CONTENT_TYPE,
				"Content-Type must be '" . \apiTools::MIME_TYPE_APPLICATION_JSON . "'",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$inputRepRawData = $request->getRequestBody();

		// assume that empty bodies are not allowed
		if ($inputRepRawData === false || is_null($inputRepRawData) || strlen(trim($inputRepRawData)) == 0) {
			if ($allowEmpty) {
				return [];
			}

			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_REQUEST_BODY,
				'Request body is empty or missing',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $this->deserializeFromStringToArray($inputRepRawData);
	}

	private function deserializeFromStringToArray(?string $inputRepRawData): array
	{
		$inputRep = json_decode($inputRepRawData, true);
		if (is_null($inputRep) || !is_array($inputRep)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_JSON_INPUT,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// make sure that the array contains string based keys. In PHP, associative arrays and sequential arrays
		// are treated with the same array data type so we need to make sure that the user did not pass in a valid
		// JSON array (like "[1,2,3]").
		if (generalTools::isArrayWithAnyNumericKey($inputRep)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_JSON_INPUT,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $inputRep;
	}
}

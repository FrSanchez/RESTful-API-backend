<?php
namespace Api\Framework;

use Api\Exceptions\ApiException;
use ApiErrorLibrary;
use Pardot\File\FilesGlobalFileInputContent;
use Pardot\File\ParameterFileInputContent;
use piWebRequest;
use RESTClient;
use RuntimeException;
use sfConfig;
use stringTools;

/**
 * Wraps the $_FILES collection so that it can be mocked in tests and provide additional functionality.
 *
 * Class ApiRequestFiles
 * @package Api\Framework
 */
class ApiRequestFiles
{
	public function __construct(int $maxAttachmentsAllowed = 1)
	{
		$this->validateMaxAttachmentsAllowed($maxAttachmentsAllowed);
	}

	/**
	 * Equivalent of calling $_FILE[$parameterName] however any case can be specified for the parameter. A null value
	 * is returned when no file can be found with the given parameter.
	 * @param piWebRequest $piWebRequest
	 * @param string $parameterName The name of the parameter name associated to the file.
	 * @return FileInput|null The file input if the name is found or null.
	 */
	public function getFileInputByName(piWebRequest $piWebRequest, string $parameterName): ?FileInput
	{
		// In tests, we allow the file to be specified in a request parameter instead of in the $_FILES global.
		if (sfConfig::get('app_file_actions_allow_file_in_parameter', false) && $piWebRequest->hasParameter('upload_file')) {
			return $this->getFileInputUsingParameter($piWebRequest, $parameterName);
		}

		// check the exact key match
		if (array_key_exists($parameterName, $_FILES)) {
			return new FileInput(new FilesGlobalFileInputContent($parameterName));
		}

		// check for any case
		$lowerFormField = strtolower($parameterName);

		// Get the file from the $_FILES array
		$found = false;
		$foundKey = null;
		$foundValue = null;
		foreach ($_FILES as $fieldKey => $fieldValue) {
			if (strtolower($fieldKey) === $lowerFormField) {
				$found = true;
				$foundKey = $fieldKey;
				$foundValue = $fieldValue;
				break;
			}
		}
		if (!$found || is_null($foundValue) || !is_array($foundValue) || count($foundValue) == 0) {
			return null;
		}
		return new FileInput(new FilesGlobalFileInputContent($foundKey));
	}

	private function getFileInputUsingParameter(piWebRequest $piWebRequest, string $fileKeyName): FileInput
	{
		$tmpName = $piWebRequest->getParameter('upload_file');
		if (stringTools::isNullOrBlank(trim($tmpName))) {
			throw new RuntimeException("The upload_file parameter is specified but the value is empty. Either remove the parameter or specify a valid path.");
		}
		return new FileInput(new ParameterFileInputContent($tmpName, $fileKeyName));
	}

	private function validateMaxAttachmentsAllowed(int $maxAttachmentsAllowed)
	{
		if (count($_FILES) > $maxAttachmentsAllowed) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_TOO_MANY_FILES, null, RESTClient::HTTP_BAD_REQUEST);
		}
	}
}

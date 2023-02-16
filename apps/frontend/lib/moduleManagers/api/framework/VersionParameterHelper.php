<?php
namespace Api\Framework;

use Api\Exceptions\ApiException;
use piWebRequest;
use RuntimeException;
use stringTools;

class VersionParameterHelper
{
	const PARAM_PATH_VERSION = '_path_version';

	/**
	 * Retrieves the version parameter from the path.
	 * @param piWebRequest $request The request to pull the version from.
	 * @param string $parameterName The name of the parameter. Defaults to "_path_version".
	 * @return ?int The version or null if the version is not specified or invalid.
	 * @throws ApiException When the parameter is not valid or can't be found.
	 */
	public static function getVersionFromRequest(piWebRequest $request, string $parameterName = self::PARAM_PATH_VERSION): ?int
	{
		$versionParamValue = $request->getParameter($parameterName);
		if (preg_match('/^[vV]([0-9]+)$/', $versionParamValue, $matches) == 0) {
			return null;
		}

		return (int)$matches[1];
	}

	/**
	 * Verifies if the string specified is in a valid version format (v#).
	 * @param string $versionAsString
	 * @return bool
	 */
	public static function isValidVersionString(string $versionAsString): bool
	{
		return preg_match('/^[vV]([0-9]+)$/', $versionAsString) == 1;
	}

	/**
	 * Parses a string in version format (v#) into an integer. If the string is not valid, then an exception is thrown.
	 * @param string $versionAsString
	 * @return int
	 */
	public static function parseVersionString(string $versionAsString): int
	{
		if (preg_match('/^[vV]([0-9]+)$/', $versionAsString, $matches) == 0) {
			throw new RuntimeException("Version is not in expected format.");
		}

		return (int)$matches[1];
	}

	/**
	 * Retrieves the version parameter from the path of a request to a v3 or v4 endpoint.
	 * @param piWebRequest $request The request to pull the version from.
	 * @param bool|null $outHasError Output parameter that is set to true if there was an error while parsing the version.
	 * @return int|null Returns the version number, null if not specified or an error occurred. See the value in $outHasError
	 * to determine if error or not specified.
	 */
	public static function getVersionFromV3OrV4Request(piWebRequest $request, ?bool &$outHasError): ?int
	{
		$versionParamValue = $request->getParameter('version');
		if (stringTools::isNullOrBlank($versionParamValue)) {
			// in v3/v4, null and empty string versions are allowed.
			// treat empty string as an equivalent to null
			$outHasError = false;
			return null;
		}

		// the version must be an integer
		if (preg_match('/^(1|2|3|4)$/', $versionParamValue, $matches) == 0) {
			$outHasError = true;
			return null;
		}

		$outHasError = false;
		return (int)$matches[1];
	}
}

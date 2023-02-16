<?php

class IntrospectionUrlHelper
{
	const VALID = 1;
	const INVALID_NOT_URL = -1;
	const INVALID_NOT_HTTPS = -2;

	/**
	 * Validates the given introspection URL and returns a valid or invalid constant value.
	 * @param string $introspectionUrl
	 * @return int
	 */
	public static function validateUrl(string $introspectionUrl): int
	{
		$introspectionUrlStartsWithHttp = stringTools::startsWith(strtolower($introspectionUrl), 'http://');
		$introspectionUrlStartsWithHttps = stringTools::startsWith(strtolower($introspectionUrl), 'https://');
		if (!$introspectionUrlStartsWithHttp && !$introspectionUrlStartsWithHttps) {
			PardotLogger::getInstance()->error("API AccessToken Auth failure: Configured introspect url does not begin with http or https.");
			return self::INVALID_NOT_URL;
		} else if (sfConfig::get('app_api_introspection_require_https', true) && !$introspectionUrlStartsWithHttps) {
			PardotLogger::getInstance()->error("API AccessToken Auth failure: Configured introspect url not https.");
			return self::INVALID_NOT_HTTPS;
		}
		return self::VALID;
	}

	/**
	 * Determines if the introspection URL is valid.
	 * @param string $introspectionUrl
	 * @return bool
	 */
	public static function isValidUrl(string $introspectionUrl): bool
	{
		return self::validateUrl($introspectionUrl) == self::VALID;
	}
}

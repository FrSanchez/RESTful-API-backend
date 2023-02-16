<?php

class ApiVersionLogger
{
	const CACHE_KEY_ROOT = 'api:version:action_ids';
	const CACHE_KEY_DELIMITER = ':';

	/**
	 * @param string $module
	 * @param string|null $action
	 * @param string|null $version
	 */
	public static function log(string $module, $action, $version)
	{
		if (is_null($version) || !(generalTools::isFloatValue($version) || generalTools::isIntegerValue($version))) {
			$actionAsText = is_null($action) ? '<null>' : (string)$action;
			$versionAsText = is_null($version) ? '<null>' : (string)$version;
			$context = [
				'module' => $module,
				'action' => $actionAsText,
				'version' => $versionAsText,
				'moduleActionVersion' => "{$module}:{$actionAsText}:{$versionAsText}",
			];
			$loggerInstance = PardotLogger::getInstance();
			$loggerInstance->addTags($context);

			try {
				// Cache the "module:action:version" for 30 mins to reduce the number of log messages since we don't
				// really need to know about the number of times called, just what is being called.
				// Using cache instead of random sampling because I want to make sure to log all cases even when they
				// are infrequent.
				$cacheManager = new CacheManager(RedisManager::DATABASE_CACHE);
				$cacheKey = self::createCacheKey(self::CACHE_KEY_ROOT, $module, $actionAsText, $versionAsText);
				if (is_null($cacheManager->get($cacheKey))) {
					$loggerInstance->error("Request was deemed to be unversioned and will be rejected. module: {$module}, action: $actionAsText, version: $versionAsText");

					// set the cache key for 30 mins
					$cacheManager->set($cacheKey, 'true', 1800);
				}
			} catch (Exception $exception) {
				$loggerInstance->error("Request was deemed to be unversioned and will be rejected. module: {$module}, action: $actionAsText, version: $versionAsText");
			}
		}
	}

	private static function createCacheKey(string... $parts): string
	{
		return join(self::CACHE_KEY_DELIMITER, array_map(function ($v) {
			return strtr($v, self::CACHE_KEY_DELIMITER, '_');
		}, $parts));
	}

}

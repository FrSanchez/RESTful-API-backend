<?php
namespace Api\Framework;

use CacheManager;
use EncryptionManager;
use EncryptionException;
use RedisManager;

class EncryptionSecretStore
{
	private const ACCESS_TOKEN_CACHE_SECRET_TTL_SECONDS = 60 * 60 * 2;  // 2 hour cache of these secrets (encrypted) in redis has been blessed by prod sec (Brent York).
	private const KEY_PREFIX = "API-SFATC-Secret";

	private $cacheManager;

	public function __construct()
	{
		$this->cacheManager = new CacheManager(RedisManager::DATABASE_CACHE);
	}

	/**
	 * Gets the encryption secret for a specific account. This is "unstable" in that a secret expires after 2hrs so this
	 * shouldn't be used for encrypting values that will last longer than 2hrs! The primary use case for this encryption
	 * secret is for sensitive information in the cache key of another cache value.
	 *
	 * @param int $accountId
	 * @param string|null $secretName
	 * @param int|null $timeout
	 * @return string
	 * @throws EncryptionException
	 */
	public function getUnstableEncryptionSecretForAccount(int $accountId, ?string $secretName = null, ?int $timeout = null): string
	{
		return $this->getEncryptionSecret( $secretName ? "{$secretName}-{$accountId}" : $accountId, $accountId, $timeout);
	}

	/**
	 * @param int $accoutId
	 * @param string|null $secretName
	 */
	public function discardUnstableEncryptionSecretForAccount(int $accountId, ?string $secretName = null)
	{
		$this->discardEncryptionSecret($secretName ? "{$secretName}-{$accountId}" : $accountId);
	}

	/**
	 * Gets the encryption secret for use when no account is available (like before authentication). This is pretty rare
	 * so it's best to use the "forAccount" version of the method instead. This is "unstable" in that a secret expires
	 * after 2hrs so this shouldn't be used for encrypting values that will last longer than 2hrs! The primary use case
	 * for this encryption secret is for sensitive information in the cache key of another cache value.
	 *
	 * @param string|null $secretName
	 * @return string
	 * @throws EncryptionException
	 */
	public function getUnstableGlobalEncryptionSecret(?string $secretName = null): string
	{
		return $this->getEncryptionSecret($secretName ? "{$secretName}-global" : 'global');
	}

	/**
	 * @param string $keySuffix
	 * @param int $accountId
	 * @return string
	 * @throws EncryptionException
	 */
	private function getEncryptionSecret(string $keySuffix, ?int $accountId = null, ?int $timeout = null): string
	{
		$encryptionManager = $this->getEncryptionManager($accountId);
		$prefix = self::KEY_PREFIX;
		$secretKey = "{$prefix}-{$keySuffix}";
		$encryptedSecret = $this->cacheManager->get($secretKey);
		if (!$encryptedSecret) {
			\GraphiteClient::increment('encryption.customerkey-api.encryption', 0.01);
			$encryptedSecret = $this->generateEncryptedSecureKey($accountId, $encryptionManager);
			$this->cacheManager->setCache($secretKey, $encryptedSecret, $timeout ?: self::ACCESS_TOKEN_CACHE_SECRET_TTL_SECONDS);
		}
		\GraphiteClient::increment('encryption.customerkey-api.decryption', 0.01);

		// Yes, there is technically a race condition, but the worst that can happen is a cache miss.
		return $encryptionManager->decrypt($encryptedSecret, "W-9960302");
	}

	/**
	 * @param string $keySuffix
	 */
	private function discardEncryptionSecret(string $keySuffix)
	{
		$prefix = self::KEY_PREFIX;
		$secretKey = "{$prefix}-{$keySuffix}";
		$this->cacheManager->clearCache($secretKey);
	}

	private function getEncryptionManager(?int $accountId = null): EncryptionManager
	{
		if (!is_null($accountId)) {
			return \piAccountTable::getInstance()->retrieveById($accountId)->getCustomerEncryptionManager();
		} else {
			return new \DatabasePasswordEncryptionManager();
		}
	}

	private function generateEncryptedSecureKey(?int $accountId, EncryptionManager $encryptionManager): string
	{
		if (!is_null($accountId)) {
			return $encryptionManager->generateEncryptedSecureKey();
		} else {
			return $encryptionManager->generateEncryptedCustomerKey();
		}
	}
}

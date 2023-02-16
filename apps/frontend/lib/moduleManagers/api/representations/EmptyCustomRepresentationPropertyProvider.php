<?php
namespace Api\Representations;

/**
 * {@see CustomRepresentationPropertyProvider} that returns an empty set of custom properties. This is used when the
 * account does not provide any custom representation properties.
 */
class EmptyCustomRepresentationPropertyProvider implements CustomRepresentationPropertyProvider
{
	private static self $instance;

	public function getAdditionalProperties(int $version, int $accountId): array
	{
		return [];
	}

	public static function getInstance(): self
	{
		if (!isset(self::$instance)) {
			self::$instance = new EmptyCustomRepresentationPropertyProvider();
		}
		return self::$instance;
	}
}

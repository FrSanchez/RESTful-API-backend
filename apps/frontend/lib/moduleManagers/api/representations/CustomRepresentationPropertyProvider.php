<?php
namespace Api\Representations;

/**
 * Provides custom representation properties that are only allowed for a specific account. This allows for custom
 * properties to be dynamically constructed based on the account settings, preferences or database tables, like Prospect
 * custom fields. The results of this provider are mixed into the {@see RepresentationDefinition}, which returns both
 * statically defined properties and properties provided by this interface.
 */
interface CustomRepresentationPropertyProvider
{
	/**
	 * Gets the list of custom property definitions that are added to the representation definition for the specific
	 * account. If the account does not have any dynamic properties, then an empty array should be returned. It's up
	 * to the implementation to provide any caching of values constructed from inefficient sources, like the database.
	 * @param int $version
	 * @param int $accountId
	 * @return RepresentationPropertyDefinition[]
	 */
	public function getAdditionalProperties(int $version, int $accountId): array;
}

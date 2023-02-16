<?php
namespace Api\Representations;

interface RepresentationDefinitionCatalog
{
	/**
	 * @param int $version
	 * @param int $accountId
	 * @return string[]
	 */
	public function getRepresentationNames(int $version, int $accountId) : array;

	/**
	 * Gets the {@see RepresentationDefinition} instance for {@see ErrorRepresentation}. This doesn't use the
	 * standard {@see findRepresentationDefinitionByName} method since an error can be thrown before the account ID
	 * is known.
	 * @param int $version
	 * @return RepresentationDefinition
	 */
	public function getErrorRepresentationDefinition(int $version): RepresentationDefinition;

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $representationName
	 * @return bool|RepresentationDefinition
	 */
	public function findRepresentationDefinitionByName(int $version, int $accountId, string $representationName);
}

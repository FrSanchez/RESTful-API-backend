<?php
namespace Api\Serialization;

interface RepresentationJsonSerializerFactory
{
	/**
	 * Gets the {@see RepresentationJsonSerializer} instance for {@see ErrorRepresentation}. This doesn't use the
	 * standard {@see getRepresentationJsonSerializer} method since an error can be thrown before the account ID
	 * is known.
	 * @param int $version
	 * @return RepresentationJsonSerializer
	 */
	public function getErrorRepresentationJsonSerializer(int $version): RepresentationJsonSerializer;

	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $representationName
	 * @return RepresentationJsonSerializer
	 */
	public function getRepresentationJsonSerializer(int $version, int $accountId, string $representationName): RepresentationJsonSerializer;
}

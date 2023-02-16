<?php
namespace Api\Deserialization;

interface RepresentationJsonDeserializerFactory
{
	/**
	 * @param int $version
	 * @param int $accountId
	 * @param string $representationName
	 * @return RepresentationJsonDeserializer
	 */
	public function getRepresentationJsonDeserializer(int $version, int $accountId, string $representationName): RepresentationJsonDeserializer;
}

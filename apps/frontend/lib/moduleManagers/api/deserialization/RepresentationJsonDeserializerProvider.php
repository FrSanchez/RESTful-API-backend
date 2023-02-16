<?php
namespace Api\Deserialization;

/**
 * Provider of instances of {@see RepresentationJsonDeserializer}. This is useful for passing around a lazy loaded
 * version of the deserializer.
 */
interface RepresentationJsonDeserializerProvider
{
	public function get(): RepresentationJsonDeserializer;
}

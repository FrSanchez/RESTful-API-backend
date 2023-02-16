<?php
namespace Api\Deserialization;

/**
 * Container of {@see RepresentationJsonDeserializer}. Similar to {@see RepresentationJsonDeserializerFactory} in that
 * this can be used to retrieve instances of {@see RepresentationJsonDeserializer} however this container is stateful
 * for the version and account associated to this container. Having the state associated to the container is useful
 * when passing the container as an argument (instead of passing version, account and factory).
 */
interface RepresentationJsonDeserializerContainer
{
	/**
	 * Gets the {@see RepresentationJsonDeserializer} instance related to the given representation name. If no
	 * deserializer is associated to the given representation, then an exception is thrown.
	 * @param string $representationName
	 * @return RepresentationJsonDeserializer
	 */
	public function getRepresentationJsonDeserializer(string $representationName): RepresentationJsonDeserializer;
}

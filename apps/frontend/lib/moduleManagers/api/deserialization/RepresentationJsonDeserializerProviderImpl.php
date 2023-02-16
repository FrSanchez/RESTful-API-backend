<?php
namespace Api\Deserialization;

/**
 * Provider of {@see RepresentationJsonDeserializer} instances backed by a {@see RepresentationJsonDeserializerContainer}.
 */
class RepresentationJsonDeserializerProviderImpl implements RepresentationJsonDeserializerProvider
{
	private RepresentationJsonDeserializerContainer $container;
	private string $representationName;
	private RepresentationJsonDeserializer $instance;

	public function __construct(
		RepresentationJsonDeserializerContainer $container,
		string $representationName
	)
	{
		$this->container = $container;
		$this->representationName = $representationName;
	}

	public function get(): RepresentationJsonDeserializer
	{
		// Check local cache to see if it's been instantiated yet
		if (!isset($this->instance)) {
			$this->instance = $this->container->getRepresentationJsonDeserializer($this->representationName);
		}
		return $this->instance;
	}
}

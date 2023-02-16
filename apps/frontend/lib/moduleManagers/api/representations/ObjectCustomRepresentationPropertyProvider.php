<?php
namespace Api\Representations;

use Api\Objects\StaticObjectDefinition;

/**
 * {@see CustomRepresentationPropertyProvider} that retrieves custom representation properties from the objects
 * custom field provider.
 */
class ObjectCustomRepresentationPropertyProvider implements CustomRepresentationPropertyProvider
{
	private StaticObjectDefinition $staticObjectDefinition;

	public function __construct(StaticObjectDefinition $staticObjectDefinition)
	{
		$this->staticObjectDefinition = $staticObjectDefinition;
	}

	public function getAdditionalProperties(int $version, int $accountId): array
	{
		$staticFieldDefinitions = $this->staticObjectDefinition->getCustomFieldProvider()->getAdditionalFields($accountId, $version);
		$representationProperties = [];
		foreach ($staticFieldDefinitions as $staticFieldDefinition) {
			$isReadable = $staticFieldDefinition->isReadOnly() || !$staticFieldDefinition->isWriteOnly();
			$isWriteable = $staticFieldDefinition->isWriteOnly() || !$staticFieldDefinition->isReadOnly();

			$representationProperties[] = new RepresentationPropertyDefinition(
				$staticFieldDefinition->getName(),
				$staticFieldDefinition->getDataType(),
				$isReadable,
				$isWriteable,
				$staticFieldDefinition->isRequired()
			);
		}
		return $representationProperties;
	}

}

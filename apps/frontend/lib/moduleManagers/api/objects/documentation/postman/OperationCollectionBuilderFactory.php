<?php
namespace Api\Objects\Postman;

use Api\Objects\StaticObjectDefinition;
use FeatureFlagGroup;

class OperationCollectionBuilderFactory
{
	/**
	 * @param StaticObjectDefinition $objectDefinition
	 * @param int $version
	 * @return OperationCollectionBuilder
	 */
	public function createOperationBuilder(StaticObjectDefinition $objectDefinition, int $version, FeatureFlagGroup  $featureFlagGroup)
	{
		switch ($objectDefinition->getType()) {
			case 'ExternalActivity':
				return new ExternalActivityOperationCollectionBuilder($objectDefinition, $version, $featureFlagGroup);
			case 'Import':
				return new ImportOperationCollectionBuilder($objectDefinition, $version, $featureFlagGroup);
			case 'Export':
				return new ExportOperationCollectionBuilder($objectDefinition, $version, $featureFlagGroup);
			default:
				return new OperationCollectionBuilder($objectDefinition, $version, $featureFlagGroup);
		}
	}
}

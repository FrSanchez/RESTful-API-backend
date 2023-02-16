<?php
namespace Api\Objects\Postman;

use Api\Objects\StaticObjectDefinition;
use FeatureFlagGroup;
use stdClass;

class PostmanObjectBuilder
{

	private StaticObjectDefinition $objectDefinition;
	private int $version;
	private string $name;
	private FeatureFlagGroup $featureFlagGroup;

	/**
	 * @param StaticObjectDefinition $objectDefinition
	 * @param int $version
	 */
	public function __construct(StaticObjectDefinition $objectDefinition, int $version, FeatureFlagGroup  $featureFlagGroup)
	{
		$this->objectDefinition = $objectDefinition;
		$this->version = $version;
		$this->name = preg_replace('/(?<!^)([A-Z])/', ' \\1', $objectDefinition->getType());
		$this->featureFlagGroup = $featureFlagGroup;
	}

	/**
	 * @return Operation[]
	 */
	private function buildOperationsAndActions(): array
	{
		$factory = new OperationCollectionBuilderFactory();
		$operationBuilder = $factory->createOperationBuilder($this->objectDefinition, $this->version, $this->featureFlagGroup);
		return $operationBuilder->build();
	}

	public function build(): stdClass
	{
		$operations = $this->buildOperationsAndActions();
		$item = new stdClass();
		$item->name = $this->name;
		$item->item = $operations;
		$item->protocolProfileBehavior = new stdClass();
		return $item;
	}
}

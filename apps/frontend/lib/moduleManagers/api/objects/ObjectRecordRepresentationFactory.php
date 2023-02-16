<?php
namespace Api\Objects;

use Api\Representations\Representation;
use Api\Representations\RepresentationBuilderContext;

interface ObjectRecordRepresentationFactory
{
	/**
	 * Given an object definition and the record data, create a representation for the record.
	 *
	 * @param ObjectDefinition $objectDefinition
	 * @param array $recordData
	 * @param array|null $customFieldData
	 * @param RepresentationBuilderContext $operationContext
	 * @return Representation
	 */
	public function createRecordRepresentationForObjectFromArray(ObjectDefinition $objectDefinition, array $recordData, RepresentationBuilderContext $context): Representation;
}

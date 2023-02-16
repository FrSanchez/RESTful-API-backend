<?php
namespace Api\Representations;

interface StaticRepresentationDefinitionCatalog
{
	/**
	 * @return string[]
	 */
	public function getRepresentationNames() : array;

	/**
	 * @param string $representationName
	 * @return bool|StaticRepresentationDefinition
	 */
	public function findRepresentationDefinitionByName(string $representationName);
}

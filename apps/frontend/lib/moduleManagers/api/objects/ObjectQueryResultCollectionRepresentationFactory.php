<?php
namespace Api\Objects;

use Api\Representations\Representation;

interface ObjectQueryResultCollectionRepresentationFactory
{
	/**
	 * Creates the QueryResultCollection representation instance for the given object and the given representations.
	 * @param ObjectDefinition $objectDefinition
	 * @param Representation[] $representations
	 * @param string|bool|null $nextPageToken
	 * @param string|bool|null $nextPageUrl
	 * @return Representation The QueryResultCollection representation
	 */
	public function createQueryResultCollectionForObject(ObjectDefinition $objectDefinition, array $representations, $nextPageToken, $nextPageUrl): Representation;
}

<?php
namespace Api\Representations;

use Api\Representations\RepresentationBuilderContext;

/**
 * Represents an input or output object of the API.
 *
 * Interface Representation
 * @package Api\Representations
 */
interface Representation
{
	/**
	 * @param array $recordAsArray
	 * @param RepresentationBuilderContext $context
	 * @return Representation
	 * @throws RepresentationException
	 */
	public static function createFromArray(array $recordAsArray, RepresentationBuilderContext $context);
}

<?php
namespace Api\Objects\Doctrine;

/**
 * Integration from the Object framework to provide update of records related to objects.
 *
 * Class DoctrineUpdateModifier
 * @package Api\Objects\Doctrine
 */
interface DoctrineUpdateModifier
{
	/**
	 * Partially update - Update the data received from user during the request.
	 *
	 * @param DoctrineUpdateContext $updateContext
	 */
	public function partialUpdateRecord(DoctrineUpdateContext $updateContext): void;
}

<?php
namespace Api\Objects\Doctrine;

use Doctrine_Exception;

/**
 * Integration from the Object framework to provide creation of new records related to objects.
 *
 * Class DoctrineCreateModifier
 * @package Api\Objects\Doctrine
 */
interface DoctrineCreateModifier
{
	/**
	 * Saves the data received from user during the request.
	 * Created_by field will need to be set if that field exists.
	 *
	 * @param DoctrineCreateContext $createContext
	 * @return array Returns primary key as an array, where key is the field and value is value of the field.
	 * @throws Doctrine_Exception
	 */
	public function saveNewRecord(DoctrineCreateContext $createContext): array;
}

<?php

namespace Api\Objects\Doctrine;

abstract class DoctrineDeleteModifier
{
	/**
	 * Pre-delete hook for delete endpoints. This function will be called before deleting/archiving the record.
	 * All checks related to the dependency have already been done before calling this function.
	 *
	 * @param DoctrineDeleteContext $deleteContext
	 */
	abstract public function preDelete(DoctrineDeleteContext $deleteContext): void;

	/**
	 * Post-delete hook for delete endpoints. This function will be called after deleting/archiving the record has completed.
	 * @param DoctrineDeleteContext $deleteContext
	 */
	abstract public function postDelete(DoctrineDeleteContext $deleteContext): void;

	/**
	 * When this function returns true, the framework will handle the deletion logic between the preDelete and postDelete calls
	 * When false, the framework expects the doctrine modifier to do the actual delete
	 * @return bool
	 */
	public function allowFrameworkDelete(): bool
	{
		return true;
	}
}

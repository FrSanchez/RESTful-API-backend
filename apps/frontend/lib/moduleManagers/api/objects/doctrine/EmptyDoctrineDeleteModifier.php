<?php
namespace Api\Objects\Doctrine;

use Singleton;
final class EmptyDoctrineDeleteModifier extends DoctrineDeleteModifier
{
	use Singleton;

	/**
	 * @inheritDoc
	 */
	public function preDelete(DoctrineDeleteContext $deleteContext): void
	{
		// Intentionally left blank
	}

	/**
	 * @inheritDoc
	 */
	public function postDelete(DoctrineDeleteContext $deleteContext): void
	{
		// Intentionally left blank
	}

}

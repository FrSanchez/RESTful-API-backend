<?php
namespace Api\Objects\Collections;

abstract class CollectionSelection
{
	protected CollectionDefinition $collectionDefinition;

	public function __construct(CollectionDefinition $collectionDefinition)
	{
		$this->collectionDefinition = $collectionDefinition;
	}

	/**
	 * @return CollectionDefinition
	 */
	public final function getCollectionDefinition(): CollectionDefinition
	{
		return $this->collectionDefinition;
	}

	public final function getCollectionName(): string
	{
		return $this->collectionDefinition->getName();
	}

	public abstract function getBulkDataProcessorClass(): string;
}

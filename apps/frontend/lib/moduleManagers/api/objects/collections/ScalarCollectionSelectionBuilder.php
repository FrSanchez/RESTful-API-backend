<?php

namespace Api\Objects\Collections;

class ScalarCollectionSelectionBuilder
{
	private CollectionDefinition $collectionDefinition;

	public function __construct(CollectionDefinition $collectionDefinition)
	{
		$this->collectionDefinition = $collectionDefinition;
	}

	public function build(): ScalarCollectionSelection
	{
		return new ScalarCollectionSelection($this->collectionDefinition);
	}
}

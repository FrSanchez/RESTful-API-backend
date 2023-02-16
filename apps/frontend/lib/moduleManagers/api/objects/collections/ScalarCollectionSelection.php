<?php

namespace Api\Objects\Collections;

use Api\DataTypes\DataType;
use RuntimeException;

/**
 * Represents a selection of a scalar collection.
 */
class ScalarCollectionSelection extends CollectionSelection
{
	private DataType $itemDataType;

	public function __construct(CollectionDefinition $collectionDefinition)
	{
		parent::__construct($collectionDefinition);

		$itemType = $collectionDefinition->getItemType();
		if ($itemType instanceof ScalarItemTypeDefinition) {
			$this->itemDataType = $itemType->getDataType();
		} else {
			throw new RuntimeException('Unknown itemType specified: ' . get_class($itemType));
		}
	}

	public function getDataType(): DataType
	{
		return $this->itemDataType;
	}

	public function getBulkDataProcessorClass(): string
	{
		return $this->collectionDefinition->getBulkDataProcessorClass();
	}
}

<?php

namespace Api\Objects\Postman;

class ExternalActivityOperationCollectionBuilder extends OperationCollectionBuilder
{
	/**
	 * @return string[]
	 */
	protected function getOperationNames(): array
	{
		return ["create", "read", "update", "delete", "query", "ingestion"];
	}

	/**
	 * @param $operationName
	 * @return bool
	 */
	public function canDoOperation($operationName): bool
	{
		if ($operationName == 'ingestion') {
			return true;
		}
		return parent::canDoOperation($operationName);
	}

	public function getOperationBuilder(string $operationName): OperationBuilder
	{
		if ($operationName == 'ingestion') {
			return new ExternalActivityIngestionOperationBuilder($this->objectDefinition, $this->version);
		}
		return parent::getOperationBuilder($operationName);
	}
}

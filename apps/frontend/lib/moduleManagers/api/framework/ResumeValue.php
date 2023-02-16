<?php

namespace Api\Framework;

use Api\DataTypes\ConversionContext;
use Api\Objects\Query\OrderByPair;
use JsonSerializable;
use ReturnTypeWillChange;
use RuntimeException;
use PardotLogger;

class ResumeValue implements JsonSerializable
{
	private array $fieldValues;

	public function getFieldNames()
	{
		return array_keys($this->fieldValues);
	}

	public function getFieldValue(string $fieldName)
	{
		if (!array_key_exists($fieldName, $this->fieldValues)) {
			throw new RuntimeException($fieldName . ' is not valid');
		}
		return $this->fieldValues[$fieldName];
	}

	public function __construct(array $fieldValues)
	{
		$this->fieldValues = $fieldValues;
	}

	/**
	 * Used by internal json serialization to obtain the list of non-public fields that will get serialized
	 * @return array|mixed
	 * @inheritDoc
	 * @PHP8Upgrade Fix return value
	 */
	#[ReturnTypeWillChange]
	public function jsonSerialize()
	{
		return $this->fieldValues;
	}

	/**
	 * @param array $lastRecord
	 * @param OrderByPair[] $orderByPairs
	 * @param ConversionContext $conversionContext
	 * @return ResumeValue
	 */
	public static function buildResumeValue(array $lastRecord, array $orderByPairs, ConversionContext $conversionContext): ResumeValue
	{
		$resumeValues['id'] = $lastRecord['id'];
		foreach ($orderByPairs as $pair) {
			$field = $pair->getFieldDefinition()->getName();
			if (!array_key_exists($field, $lastRecord)) {
				PardotLogger::getInstance()->error("While building nextPageToken, the field {$field} was missing from the lastRecord array");
				throw new RuntimeException("can't build a nextPageToken: The {$field} is missing from lastRecord");
			}

			$value = $lastRecord[$field];
			$resumeValues[$field] = $pair->getFieldDefinition()->getDataType()->convertServerValueToApiValue($value, $conversionContext);
		}

		return new ResumeValue($resumeValues);
	}
}

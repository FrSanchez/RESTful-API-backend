<?php

namespace Api\DataTypes;

use Api\Representations\PolymorphicRepresentation;
use TypedXMLOrJSONWriter;

/**
 * Not a real data type. Used to handle polymorphic representations during code generation
 */
class PolymorphicDataType implements DataType
{
	public const NAME = 'polymorphic';
	private PolymorphicRepresentation $representation;

	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * @param PolymorphicRepresentation $representation
	 */
	public function __construct(PolymorphicRepresentation $representation)
	{
		$this->representation = $representation;
	}

	public function getRepresentation(): PolymorphicRepresentation
	{
		return $this->representation;
	}

	/**
	 * @inheritDoc
	 */
	public function validateParameterValue(string $userValue, ConversionContext $context): array
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		// Intentionally left blank - only used during code generation.
	}

	/**
	 * @inheritDoc
	 */
	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function convertDatabaseValueToServerValue($dbValue)
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function isServerValueType($value): bool
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
		// Intentionally left blank - only used during code generation
	}

	/**
	 * @inheritDoc
	 */
	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		// Intentionally left blank - only used during code generation
	}
}

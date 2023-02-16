<?php
namespace Api\DataTypes;

use RuntimeException;
use TypedXMLOrJSONWriter;

/**
 * DataType that are references to a Representation.
 *
 * Class RepresentationReferenceDataType
 * @package Api\DataTypes
 */
class RepresentationReferenceDataType implements DataType
{
	private $representationName;

	/**
	 * @param string $representationName The name of the representation.
	 */
	public function __construct(string $representationName)
	{
		$this->representationName = $representationName;
	}

	public function getName(): string
	{
		return $this->representationName;
	}

	/**
	 * Gets the name of the representation.
	 * @return string
	 */
	public function getRepresentationName(): string
	{
		return $this->representationName;
	}

	public function isServerValueType($value): bool
	{
		throw new RuntimeException("Unsupported operation on data type. Unable to determine representation instance on server.");
	}

	public function validateParameterValue(string $userValue, ConversionContext $context): array
	{
		throw new RuntimeException("Unsupported operation on data type. Representation cannot be used as parameters.");
	}

	public function convertParameterValueToServerValue(string $userValue, ConversionContext $context)
	{
		throw new RuntimeException("Unsupported operation on data type. Representation cannot be used as parameters.");
	}

	public function validateJsonValue($userValue, ConversionContext $context): array
	{
		throw new RuntimeException("Unsupported operation on data type. Representations need to use the JsonDeserializer to convert.");
	}

	public function convertJsonValueToServerValue($userValue, ConversionContext $context)
	{
		throw new RuntimeException("Unsupported operation on data type. Representations need to use the JsonDeserializer to convert.");
	}

	public function convertDatabaseValueToApiValue($dbValue, ConversionContext $context)
	{
		throw new RuntimeException("Unsupported operation on data type. Representation cannot be loaded from the DB.");
	}

	public function convertDatabaseValueToServerValue($dbValue)
	{
		return $dbValue;
	}

	public function convertServerValueToApiValue($serverValue, ConversionContext $context)
	{
		throw new RuntimeException("Unsupported operation on data type. Representations need to use the JsonSerializer to convert.");
	}

	public function writeServerValueToXmlWriter(TypedXMLOrJSONWriter $writer, ConversionContext $context, string $propertyName, $serverValue): void
	{
		throw new RuntimeException("Unsupported operation on data type. Representation cannot be written to a writer.");
	}

}

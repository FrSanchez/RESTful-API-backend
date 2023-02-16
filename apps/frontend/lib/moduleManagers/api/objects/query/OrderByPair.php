<?php
namespace Api\Objects\Query;

use Api\Objects\FieldDefinition;
use RuntimeException;

class OrderByPair
{
	// constant values are in Doctrine format
	const DIRECTION_ASC = "ASC";
	const DIRECTION_DESC = "DESC";

	private $fieldDefinition;
	private $direction;

	public function __construct(FieldDefinition $fieldDefinition, string $direction = OrderByPair::DIRECTION_ASC)
	{
		$this->fieldDefinition = $fieldDefinition;
		if (!in_array($direction, [self::DIRECTION_ASC, self::DIRECTION_DESC])) {
			// this shouldn't happen, because the orderByPair call should be done after validating the input
			throw new RuntimeException("The value for OrderByPair direction is invalid.", \ApiErrorLibrary::API_ERROR_INVALID_PARAMETER);
		}
		$this->direction = $direction;
	}

	public function getFieldDefinition(): FieldDefinition
	{
		return $this->fieldDefinition;
	}

	public function getDirection(): string
	{
		return $this->direction;
	}

	public function isAscending(): bool
	{
		return $this->direction = ManyQuery::DIRECTION_ASC;
	}

	public function __toString() : string
	{
		return "{$this->fieldDefinition->getName()} $this->direction";
	}
}

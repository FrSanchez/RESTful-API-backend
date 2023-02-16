<?php
namespace Api\Objects;

use Api\Exceptions\ApiException;
use Api\Objects\Query\OrderByPair;
use ApiErrorLibrary;
use RESTClient;
use stringTools;

class OrderByParser
{
	/**
	 * The sort order is comma delimited, where each value is a field and optional direction. If the direction isn't
	 * specified, the default is ascending ("ASC").
	 *
	 * Examples:
	 *
	 *  - "name"
	 *  - "name asc"
	 *  - "name desc"
	 *  - "name, id"
	 *
	 * @param string $value
	 * @param int $version
	 * @param ObjectDefinition $objectDefinition
	 * @return OrderByPair[]
	 */
	public function parseOrderBy(string $value, int $version, ObjectDefinition $objectDefinition): array
	{
		$value = trim($value);
		if (stringTools::isNullOrBlank($value)) {
			return [];
		}

		/** @var OrderByPair[] $orderByPairs */
		$orderByPairs = [];

		/** @var string[] $rawOrderByPairs */
		$rawOrderByPairs = explode(',', $value);
		foreach ($rawOrderByPairs as $rawOrderByPair) {
			$rawOrderByPair = trim($rawOrderByPair);
			if (stringTools::isNullOrBlank($rawOrderByPair)) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
					"orderBy. It contains an invalid or unknown orderBy: {$rawOrderByPair}",
					RESTClient::HTTP_BAD_REQUEST
				);
			}

			// separate the field name from the direction
			$spaceIndex = strpos($rawOrderByPair, ' ');
			if ($spaceIndex === false) {
				// the user didn't specify a direction so this must be just a field
				$rawFieldName = $rawOrderByPair;
				$rawDirection = null;
			} else {
				$rawFieldName = trim(substr($rawOrderByPair, 0, $spaceIndex));
				$rawDirection = trim(substr($rawOrderByPair, $spaceIndex));
			}

			// verify that the field is a known name
			$fieldDefinition = $objectDefinition->getFieldByName($rawFieldName);
			if (!$fieldDefinition) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
					"orderBy. Unknown field name specified: {$rawFieldName}",
					RESTClient::HTTP_BAD_REQUEST
				);
			}

			if (!$fieldDefinition->isSortable()) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
					"orderBy. Field name \"{$rawFieldName}\" is not sortable.",
					RESTClient::HTTP_BAD_REQUEST
				);
			}

			// verify the direction
			$orderByDirection = $this->validateAndReturnDirection($rawDirection);

			if (is_null($orderByDirection)) {
				$orderByPairs[] = new OrderByPair($fieldDefinition);
			} else {
				$orderByPairs[] = new OrderByPair($fieldDefinition, $orderByDirection);
			}
		}

		return $orderByPairs;
	}

	private function validateAndReturnDirection(?string $rawDirection): ?string
	{
		if (is_null($rawDirection)) {
			return null;
		}

		switch (trim(strtoupper($rawDirection))) {
			case OrderByPair::DIRECTION_ASC:
				return OrderByPair::DIRECTION_ASC;
			case OrderByPair::DIRECTION_DESC:
				return OrderByPair::DIRECTION_DESC;
			default:
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
					"orderBy. Unknown direction specified: {$rawDirection}. Expected " . OrderByPair::DIRECTION_ASC . ' or ' . OrderByPair::DIRECTION_DESC,
					RESTClient::HTTP_BAD_REQUEST
				);
		}
	}
}

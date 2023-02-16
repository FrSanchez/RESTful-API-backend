<?php
namespace Api\Config\Objects\TaggedObject\ExportProcedures;

use Api\Exceptions\ApiException;
use ApiErrorLibrary;
use Doctrine_Query;
use Exception;
use PardotLogger;
use piTagObjectTable;
use RESTClient;

class TagTypeArgumentHelper
{
	const TAG_TYPE_PROSPECT = "prospect";

	/**
	 * Applies Tag Type Argument to the Query.
	 * @param Doctrine_Query $query
	 * @param string|null $tagType,
	 * @param string $alias
	 * @throws Exception
	 */
	public static function applyTagTypeToQuery(Doctrine_Query $query, ?string $tagType, string $alias = ''): void
	{
		if (!self::isValidTagType($tagType)) {
			PardotLogger::getInstance()->error("The $tagType type is invalid.");
			throw new ApiException(ApiErrorLibrary::API_ERROR_UNKNOWN, null, RESTClient::HTTP_BAD_REQUEST);
		}

		if (!empty($alias)) {
			$alias .= '.';
		}

		$formattedTagType = strtolower(trim($tagType));
		$tagTypeConstant = piTagObjectTable::getConstantFromDisplayName($formattedTagType);

		$query->addWhere($alias . 'type = ?', $tagTypeConstant);
	}

	public static function validateTagType($tagType)
	{
		$isTagTypeValid = self::isValidTagType($tagType);

		if (!$isTagTypeValid) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_ARGUMENT,
				"The '$tagType' type is invalid. Specify another value and try again." ,
				RESTClient::HTTP_BAD_REQUEST);
		}
	}

	/**
	 * @param string $tagType Display name for the tag
	 * @return bool True if valid, false otherwise
	 */
	public static function isValidTagType(string $tagType) : bool
	{
		return strtolower($tagType) === self::TAG_TYPE_PROSPECT;
	}
}

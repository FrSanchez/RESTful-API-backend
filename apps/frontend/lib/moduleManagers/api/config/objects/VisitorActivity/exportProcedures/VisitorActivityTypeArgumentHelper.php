<?php
namespace Api\Config\Objects\VisitorActivity\ExportProcedures;

use Api\Exceptions\ApiException;

class VisitorActivityTypeArgumentHelper
{
	/**
	 * @param null|array $types
	 * @throws ApiException
	 */
	public static function isValidVisitorActivityType(?array $types)
	{
		if (is_null($types)) {
			return;
		}

		// Except if invalid types are provided
		$invalidTypes = array_diff($types, \VisitorActivityConstants::getAllActivityTypes(true));
		if (!empty($invalidTypes)) {
			throw new ApiException(
				\ApiErrorLibrary::API_ERROR_INVALID_PROCEDURE_ARGUMENT,
				'nonexistent type provided: ' . implode(',', $invalidTypes),
				\RESTClient::HTTP_BAD_REQUEST
			);
		}
	}
}

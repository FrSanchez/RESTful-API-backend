<?php
namespace Api\Config\Objects\EmailTemplate;

use Api\Gen\Representations\SendOptionRepresentation;
use EmailConstants;

class SenderOptionsTransformer
{
	/**
	 * Converts an array of {@see SendOptionRepresentation} instances into a string of JSON that can be saved into
	 * the database.
	 * @param SendOptionRepresentation[] $serverValue
	 * @return string
	 */
	public static function convertSendOptionRepresentationsToDbValue(?array $serverValue): ?string
	{
		if (is_null($serverValue)) {
			return null;
		}

		$optionArray = [];
		foreach ($serverValue as $sendOptionRepresentation) {
			$type = (int) $sendOptionRepresentation->getType();
			switch ($type) {
				case EmailConstants::SENDER_GENERAL_USER:
					$optionArray[] = [
						$type,
						$sendOptionRepresentation->getName(),
						$sendOptionRepresentation->getAddress(),
					];
					break;
				case EmailConstants::SENDER_SPECIFIC_USER:
					$optionArray[] = [
						$type,
						(int) $sendOptionRepresentation->getUserId(),
					];
					break;
				case EmailConstants::SENDER_PROSPECT_CUSTOM_FIELD:
					$optionArray[] = [
						$type,
						(int) $sendOptionRepresentation->getProspectCustomFieldId(),
					];
					break;
				case EmailConstants::SENDER_ACCOUNT_CUSTOM_FIELD:
					$optionArray[] = [
						$type,
						(int) $sendOptionRepresentation->getAccountCustomFieldId(),
					];
					break;
				default:
					$optionArray[] = $type;
			}

		}

		return json_encode($optionArray);
	}
}

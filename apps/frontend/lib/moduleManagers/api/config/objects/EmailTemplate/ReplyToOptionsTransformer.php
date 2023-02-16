<?php
namespace Api\Config\Objects\EmailTemplate;

use Api\Gen\Representations\ReplyToOptionRepresentation;
use EmailConstants;

class ReplyToOptionsTransformer
{
	/**
	 * Converts an array of {@see ReplyToOptionRepresentation} instances into a string of JSON that can be saved into
	 * the database.
	 * @param ReplyToOptionRepresentation[]|null $serverValue
	 * @return string
	 */
	public static function convertReplyToOptionsRepresentationToDbValue(?array $serverValue): ?string
	{
		if (empty($serverValue)) {
			return null;
		}

		$optionArray = [];
		foreach ($serverValue as $replyTopRepresentation) {
			$type = (int) $replyTopRepresentation->getType();
			switch ($type) {
				case EmailConstants::REPLY_GENERAL_ADDRESS:
					$optionArray[] = [
						$type,
						$replyTopRepresentation->getAddress()
					];
					break;
				case EmailConstants::REPLY_SPECIFIC_USER:
					$optionArray[] = [
						$type,
						$replyTopRepresentation->getUserId()
					];
					break;
				case EmailConstants::REPLY_PROSPECT_CUSTOM_FIELD:
					$optionArray[] = [
						$type,
						$replyTopRepresentation->getProspectCustomFieldId()
					];
					break;
				case EmailConstants::REPLY_ACCOUNT_CUSTOM_FIELD:
					$optionArray[] = [
						$type,
						$replyTopRepresentation->getAccountCustomFieldId()
					];
					break;
				default:
					$optionArray[] = $type;
			}

		}

		return json_encode($optionArray);
	}
}

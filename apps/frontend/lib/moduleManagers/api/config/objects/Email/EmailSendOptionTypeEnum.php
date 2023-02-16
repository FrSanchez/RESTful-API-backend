<?php

namespace Api\Config\Objects\Email;

use Api\Objects\Enums\EnumField;
use EmailConstants;
use EmailMessagePeer;

class EmailSendOptionTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
			EmailConstants::SENDER_GENERAL_USER => "general_user",
			EmailConstants::SENDER_SPECIFIC_USER => "specific_user",
			EmailConstants::SENDER_ASSIGNED_USER => "assigned_user",
			EmailConstants::SENDER_ACCOUNT_OWNER => "account_owner",
			EmailConstants::SENDER_PROSPECT_CUSTOM_FIELD => "prospect_custom_field",
			EmailConstants::SENDER_ACCOUNT_CUSTOM_FIELD => "account_custom_field",
		];
	}
}

<?php

namespace Api\Config\Objects\Email;

use Api\Objects\Enums\EnumField;
use EmailConstants;
use EmailMessagePeer;

class EmailReplyToOptionTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return [
			EmailConstants::REPLY_GENERAL_ADDRESS => "general_address",
			EmailConstants::REPLY_SPECIFIC_USER => "specific_user",
			EmailConstants::REPLY_ASSIGNED_USER => "assigned_user",
			EmailConstants::REPLY_ACCOUNT_OWNER => "account_owner",
			EmailConstants::REPLY_PROSPECT_CUSTOM_FIELD => "prospect_custom_field",
			EmailConstants::REPLY_ACCOUNT_CUSTOM_FIELD => "account_custom_field",
		];
	}
}

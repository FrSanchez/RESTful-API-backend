<?php
namespace Api\Config\Objects\EmailTemplate;

use Api\Objects\Enums\EnumField;
use EmailMessagePeer;

class EmailTemplateTypeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return array(
			EmailMessagePeer::INVALID => 'invalid',
			EmailMessagePeer::TEXT_ONLY => 'text',
			EmailMessagePeer::HTML_ONLY => 'html',
			EmailMessagePeer::TEXT_HTML => 'htmlAndText'
		);
	}
}

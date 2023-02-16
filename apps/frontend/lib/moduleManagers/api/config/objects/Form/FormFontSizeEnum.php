<?php
namespace Api\Config\Objects\Form;

use Api\Objects\Enums\EnumField;
use FormPeer;

class FormFontSizeEnum implements EnumField
{
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return FormPeer::getSelectableFontSizes(true);
	}
}

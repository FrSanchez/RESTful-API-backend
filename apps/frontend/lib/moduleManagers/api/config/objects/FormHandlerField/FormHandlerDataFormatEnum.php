<?php
namespace Api\Config\Objects\FormHandlerField;

use Api\Objects\Enums\EnumField;
use FormFieldPeer;

class FormHandlerDataFormatEnum implements EnumField {
	/**
	 * @return array
	 */
	public function getArray(): array
	{
		return FormFieldPeer::getDataFormatsArray(true);
	}
}

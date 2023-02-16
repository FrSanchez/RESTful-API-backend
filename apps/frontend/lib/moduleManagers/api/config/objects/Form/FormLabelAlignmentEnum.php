<?php
namespace Api\Config\Objects\Form;

use Api\Objects\Enums\EnumField;
use FormPeer;

class FormLabelAlignmentEnum implements EnumField
{

	public function getArray(): array
	{
		return FormPeer::getSelectableLabelAlignments(true);
	}
}

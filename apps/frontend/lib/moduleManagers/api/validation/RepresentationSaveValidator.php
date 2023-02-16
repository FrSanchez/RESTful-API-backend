<?php
namespace Api\Validation;

use Api\Representations\Representation;

interface RepresentationSaveValidator
{
	/**
	 * @param Representation $representation
	 */
	public function validateCreate(Representation $representation): void;

	/**
	 * @param Representation $representation
	 */
	public function validatePatchUpdate(Representation $representation): void;
}

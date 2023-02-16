<?php
namespace Api\Validation;

interface RepresentationValidatorCatalog
{
	/**
	 * Finds an validator for a representation of the given name.
	 *
	 * @param string $representationName The fully qualified representation name.
	 * @return RepresentationSaveValidator
	 */
	public function findSaveValidatorByClassName(string $representationName): RepresentationSaveValidator;
}

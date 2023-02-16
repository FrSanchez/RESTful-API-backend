<?php
namespace Api\Deserialization;

use Api\DataTypes\ConversionContext;
use Api\Representations\Representation;
use piWebRequest;

interface RepresentationJsonDeserializer
{
	/**
	 * @param piWebRequest $request
	 * @param ConversionContext $conversionContext
	 * @return Representation
	 */
	public function deserializeFromRequest(piWebRequest $request, ConversionContext $conversionContext): Representation;

	/**
	 * @param array $inputArray
	 * @param ConversionContext $conversionContext
	 * @return Representation
	 */
	public function deserializeFromInputArray(?array $inputArray, ConversionContext $conversionContext): ?Representation;

	/**
	 * Deserializes a representation from the multipart form given the form field name.
	 *
	 * @param piWebRequest $request
	 * @param string $formFieldName
	 * @param ConversionContext $conversionContext
	 * @param bool $allowEmptyRepresentation
	 * @return Representation
	 */
	public function deserializeFromMultipartParam(piWebRequest $request, string $formFieldName, ConversionContext $conversionContext, bool $allowEmptyRepresentation): ?Representation;
}

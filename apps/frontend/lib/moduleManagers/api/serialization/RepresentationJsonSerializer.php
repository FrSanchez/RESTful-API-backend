<?php
namespace Api\Serialization;

use Api\DataTypes\ConversionContext;
use Api\Representations\Representation;

interface RepresentationJsonSerializer
{
	/**
	 * @param Representation|null $representation
	 * @param int|null $accountId
	 * @param ConversionContext $conversionContext
	 * @return string
	 */
	public function serializeToString(?Representation $representation, ?int $accountId, ConversionContext $conversionContext): string;

	/**
	 * @param Representation $representation
	 * @param ConversionContext $conversionContext
	 * @return object|null
	 */
	public function serializeToObject(?Representation $representation, ?int $accountId, ConversionContext $conversionContext): ?object;
}

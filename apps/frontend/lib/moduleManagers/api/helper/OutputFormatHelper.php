<?php


class OutputFormatHelper
{
	const OUTPUT_FORMAT_NONE = 0;
	const OUTPUT_FORMAT_FULL = 1;
	const OUTPUT_FORMAT_SIMPLE = 2;
	const OUTPUT_FORMAT_MOBILE = 3;
	const OUTPUT_FORMAT_BULK = 4;
	/** @deprecated Use OUTPUT_FORMAT_BULK instead  */
	const OUTPUT_FORMAT_GOODDATA = 5;

	const OUTPUT_FORMAT_NAMES = [
		"bulk"     => self::OUTPUT_FORMAT_BULK,
		"full"     => self::OUTPUT_FORMAT_FULL,
		"gooddata" => self::OUTPUT_FORMAT_GOODDATA,
		"mobile"   => self::OUTPUT_FORMAT_MOBILE,
		"none"     => self::OUTPUT_FORMAT_NONE,
		"simple"   => self::OUTPUT_FORMAT_SIMPLE,
	];

	public static function getOutputFormatFromSfActions(sfActions $sfActions): int
	{
		$outputFormat = strtolower($sfActions->getRequestParameter('output'));
		if ($outputFormat && array_key_exists($outputFormat, self::OUTPUT_FORMAT_NAMES)) {
			$value = self::OUTPUT_FORMAT_NAMES[$outputFormat];

			// gooddata is deprecated so replace it with bulk instead
			if ($value == self::OUTPUT_FORMAT_GOODDATA) {
				return self::OUTPUT_FORMAT_BULK;
			}

			return $value;
		}
		return self::OUTPUT_FORMAT_FULL;
	}

	public static function getOutputFormatName(int $outputFormat): string
	{
		return array_flip(self::OUTPUT_FORMAT_NAMES)[$outputFormat];
	}
}

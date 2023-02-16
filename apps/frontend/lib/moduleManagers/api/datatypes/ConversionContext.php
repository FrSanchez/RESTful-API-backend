<?php

namespace Api\DataTypes;

/**
 * Context for converting data types. This is usually derived from apiActions but may be constructed from JSON when in
 * a different context, like in a background job.
 *
 * @package Api\DataTypes
 */
class ConversionContext
{
	/** @var \DateTimeZone $timezone */
	private $timezone;

	/** @var int $version */
	private $version;

	/**
	 * ConversionContext constructor.
	 * @param \DateTimeZone $timezone
	 * @param int $version
	 */
	public function __construct(\DateTimeZone $timezone, int $version)
	{
		$this->timezone = $timezone;
		$this->version = $version;
	}

	/**
	 * Gets the timezone used for this conversion.
	 * @return \DateTimeZone
	 */
	public function getTimezone(): \DateTimeZone
	{
		return $this->timezone;
	}

	/**
	 * Gets the version used for this conversion.
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	public function toJson(): string
	{
		return json_encode([
			'timezoneId' => $this->timezone->getName(),
			'version' => $this->version,
		]);
	}

	/**
	 * @param int $version
	 * @return static
	 */
	public static function createDefault(int $version): self
	{
		$timezone = timezone_open(date_default_timezone_get());
		return new self($timezone, $version);
	}

	public static function createFromJson(string $json): self
	{
		$jsonAsArray = json_decode($json, true);

		$timezoneId = date_default_timezone_get();
		if (array_key_exists('timezoneId', $jsonAsArray)) {
			$timezoneId = $jsonAsArray['timezoneId'];
		}
		$timezone = timezone_open($timezoneId);

		$version = 5;
		if (array_key_exists('version', $jsonAsArray)) {
			$version = $jsonAsArray['version'];
		}

		return new self($timezone, $version);
	}

	/**
	 * @param \apiActions $apiActions
	 * @return ConversionContext
	 */
	public static function createFromApiActions(\apiActions $apiActions): self
	{
		$timezone = $apiActions->getTimezoneObjectForRequest();
		return new self($timezone, $apiActions->version);
	}
}

<?php
namespace Api\Yaml;

use stringTools;
use sfYaml;
use RuntimeException;

class YamlFile
{
	/** @var string $directory */
	private $directory;

	/** @var string $fileName */
	private $fileName;

	/** @var string $name */
	private $name;

	/** @var array $yamlAsArray */
	private $yamlAsArray;

	/**
	 * YAMLFile constructor.
	 * @param string $directory
	 * @param string $fileName
	 * @throws YamlException
	 */
	public function __construct(string $directory, string $fileName)
	{
		$this->directory = $directory;
		$this->fileName = $fileName;

		if (!self::isValidateFile($fileName, $this->getFilePath())) {
			throw new YamlException("YAML file is not valid");
		}

		if (stringTools::endsWith($this->fileName, '.yaml')) {
			$this->name = substr($this->fileName, 0, -5);
		} else {
			$this->name = substr($this->fileName, 0, -4);
		}
	}

	/**
	 * @param string $fileName
	 * @param string $filePath
	 * @return bool
	 */
	public static function isValidateFile(string $fileName, string $filePath): bool
	{
		if ($fileName == '.' || $fileName == '..') {
			return false;
		}

		if (!is_file($filePath)) {
			return false;
		}

		return stringTools::endsWith($fileName, '.yaml') || stringTools::endsWith($fileName, '.yml');
	}

	/**
	 * @return string
	 */
	public function getFileName(): string
	{
		return $this->fileName;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getFilePath(): string
	{
		return join(DIRECTORY_SEPARATOR, [$this->directory, $this->fileName]);
	}

	/**
	 * @return array
	 */
	public function parseContentsAsObject(): array
	{
		if (!$this->yamlAsArray) {
			$this->yamlAsArray = sfYaml::load($this->getFilePath());
		}

		return $this->yamlAsArray;
	}

	/**
	 * @param string $regexPattern
	 * @param string|null $exceptionMessage
	 * @param string $exceptionClass
	 * @throws RuntimeException
	 */
	public function assertFilenameMatchesRegex(
		string $regexPattern,
		string $exceptionMessage = null,
		string $exceptionClass = YamlException::class
	): void {
		if (!preg_match($regexPattern, $this->getName())) {
			throw new $exceptionClass($exceptionMessage ?: "{$this->getName()} does not match the following regex patterns: {$regexPattern}");
		}
	}
}

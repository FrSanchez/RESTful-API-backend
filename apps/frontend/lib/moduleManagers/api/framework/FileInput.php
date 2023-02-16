<?php
namespace Api\Framework;

use Pardot\File\FileInputContent;
use VarHolderTrait;

/**
 * Wrapper class around a value retrieved from the $_FILES constant. This is useful to ensure type safety instead of
 * passing around an array.
 *
 * Class FileInput
 * @package Api\Framework
 */
class FileInput
{
	use VarHolderTrait;
	private FileInputContent $fileInputContent;

	public function __construct(FileInputContent $fileInputContent)
	{
		$this->fileInputContent = $fileInputContent;
		$this->initialize();
	}

	public function toFileInputContent()
	{
		return $this->fileInputContent;
	}

	/**
	 * @return mixed
	 */
	public function getName()
	{
		return $this->fileInputContent->getName();
	}

	/**
	 * @return mixed
	 */
	public function getType()
	{
		return $this->fileInputContent->getType();
	}

	/**
	 * @return mixed
	 */
	public function getSize()
	{
		return $this->fileInputContent->getSize();
	}

	/**
	 * Gets the file upload error or null if no error was specified.
	 * @see https://www.php.net/manual/en/features.file-upload.errors.php
	 * @return int|null
	 */
	public function getError()
	{
		return $this->fileInputContent->getError();
	}
}

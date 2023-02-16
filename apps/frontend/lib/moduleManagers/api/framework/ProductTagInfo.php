<?php
namespace Api\Framework;

use ProductTagConstants;

/**
 * Information about product tag
 * Class ProductTagInfo
 * @package Api\Framework
 */
class ProductTagInfo
{
	private string $name;

	public function __construct(string $name)
	{
		$this->name = $name;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getId(): ?string
	{
		return ProductTagConstants::getIdFromName($this->name);
	}
}

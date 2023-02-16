<?php
namespace Api\Objects;

class FieldsParserMapHelper
{
	private ?string $value = null;
	private array $children = [];

	public function setValue(string $value): self
	{
		$this->value = $value;
		return $this;
	}

	public function getValue(): ?string
	{
		return $this->value;
	}

	public function &getChildren(): array
	{
		return $this->children;
	}

	public function hasChildren(): bool
	{
		return count($this->children) > 0;
	}
}

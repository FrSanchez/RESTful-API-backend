<?php

namespace Api\Objects\Postman;

use Api\DataTypes\ArrayDataType;
use Api\DataTypes\BooleanDataType;
use Api\DataTypes\DataType;
use Api\DataTypes\DateTimeDataType;
use Api\DataTypes\EnumDataType;
use Api\DataTypes\FloatDataType;
use Api\DataTypes\IntegerDataType;
use Api\DataTypes\MapDataType;
use Api\Objects\StaticObjectDefinition;
use DateTime;
use DateTimeInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use stdClass;
use stringTools;

class OperationBuilder
{
	protected StaticObjectDefinition $objectDefinition;
	protected int $version;

	public function __construct(StaticObjectDefinition $objectDefinition, int $version)
	{
		$this->objectDefinition = $objectDefinition;
		$this->version = $version;
	}

	/**
	 * @param bool $useId
	 * @return array|string[]
	 */
	protected function calculatePathForUrl(bool $useId = false): array
	{
		if (empty($this->objectDefinition->getCustomUrlPath())) {
			$path = ["api", "v$this->version", "objects", $this->objectDefinition->getUrlObjectName()];
		} else {
			$path = preg_split("/\//", strtolower(str_replace('{version}', "v{$this->version}", $this->objectDefinition->getCustomUrlPath())), PREG_SPLIT_NO_EMPTY);
		}
		if ($useId) {
			$path[] = ":id";
		}
		return $path;
	}

	/**
	 * @param Url $url
	 * @param bool $useId
	 */
	protected function addPathToUrl(Url $url, bool $useId = false)
	{
		$path = $this->calculatePathForUrl($useId);
		$url->setPath($path);
	}

	/**
	 * @param Url $url
	 * @param bool $read
	 * @throws ReflectionException
	 */
	protected function addFieldsToUrlQuery(Url $url, bool $read)
	{
		$fields = [];
		foreach ($this->objectDefinition->getFields() as $field) {
			if (!$field->isCustom()) {
				if (($read || $field->isQueryable()) && !$field->isWriteOnly()) {
					switch ($field->getDataType()->getName()) {
						case ArrayDataType::NAME:
							/** @var DataType $itemDataType */
							$itemDataType = $field->getDataType()->getItemDataType();
							if (stringTools::endsWith('Representation', $itemDataType->getName())) {
								$obj = new ReflectionClass('Api\\Gen\\Representations\\' . $itemDataType->getName());
								foreach ($obj->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
									preg_match('/get(Is)*([A-Za-z]+)(Set)*/', $method->getName(), $matches, PREG_OFFSET_CAPTURE);
									if (!empty($matches) && $matches[1][1] == -1) {
										$optionName = lcfirst($matches[2][0]);
										$fields[] = "{$field->getName()}.$optionName";
									}
								}
							} else {
								$fields[] = $field->getName();
							}
							break;
						case MapDataType::NAME:
							$itemDataType = $field->getDataType()->getItemDataType();
							echo "\t*** Add extra values for map of {$itemDataType->getName()}\n";
							break;
						default:
							$fields[] = $field->getName();
					}
				}
			}
		}
		$fields = join(",", $fields);
		$url->addQuery(new Entry("fields", null, $fields, "(required) {$this->objectDefinition->getType()} fields to return", false));
	}

	/**
	 * @param $url
	 * @param $fields
	 * @return void
	 */
	protected function setFieldsToUrl($url, $fields): void
	{
		if (empty($fields)) {
			return;
		}
		$fields = join(",", $fields);
		$currentFields = $url->getQuery()[0]->getValue();
		$currentFields .= ',' . $fields;
		$url->getQuery()[0]->setValue($currentFields);
	}

	/**
	 * @param string $name
	 * @param $type
	 * @param DataType $dt
	 * @return array|false|int|stdClass|string
	 */
	protected function getValue(string $name, $type, DataType $dt)
	{
		switch ($dt->getName()) {
			case FloatDataType::NAME:
			case IntegerDataType::NAME:
				$value = 0;
				break;
			case BooleanDataType::NAME:
				$value = false;
				break;
			case DateTimeDataType::NAME:
				$value = (new DateTime())->format(DateTimeInterface::ATOM);
				break;
			case ArrayDataType::NAME:
				$value = [];
				break;
			case EnumDataType::NAME:
				$value = "enum";
				$values = $dt->getEnumValues();
				if (!empty($values)) {
					$value = $values[array_key_first($values)];
				}
				break;
			case 'ProspectRepresentation':
				$value = new stdClass();
				$value->email = "prospect@email.com";
				$value->firstName = "Name";
				break;
			default:
				$value = "{$name} value";
				break;
		}
		if (stringTools::contains(strtolower($name), 'url')) {
			$value = "https://example.com/url/path.html";
		}
		return $value;
	}

	public function build(): ?Operation
	{
		return null;
	}

	/**
	 * @param Url $url
	 */
	protected function addIdVariable(Url $url)
	{
		$url->addVariable(new Entry("id", null, "", "(required) {$this->objectDefinition->getType()} record id"));
	}

	/**
	 * @param $payload
	 * @return Body
	 */
	protected function generateRawBody($payload): Body
	{
		$body = new Body();
		$body->setMode("raw");
		$body->setRaw($payload);
		$options = new stdClass();
		$options->formdata = [];
		$options->raw = new stdClass();
		$options->raw->language = "json";
		$body->setOptions($options);
		return $body;
	}
}

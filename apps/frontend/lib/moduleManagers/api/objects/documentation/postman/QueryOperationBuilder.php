<?php

namespace Api\Objects\Postman;

use Api\DataTypes\BooleanDataType;
use Api\DataTypes\DateTimeDataType;
use Api\DataTypes\IntegerDataType;
use Api\DataTypes\StringDataType;
use Api\Objects\StaticFieldDefinition;
use DateTime;
use DateTimeInterface;
use ReflectionException;
use RESTClient;

class QueryOperationBuilder extends OperationBuilder
{
	/**
	 * @return Operation
	 * @throws ReflectionException
	 */
	protected function generateQueryOperation(): Operation
	{
		$operation = $this->generateReadOrQueryOperation(false);
		$url = $operation->getRequest()->getUrl();
		$url->addQuery(new Entry("limit", null, 200, "integer - the number of records to return. This value must be between 1 and 1000, inclusive. Default value is 200.", true));
		$url->addQuery(new Entry("nextPageToken", null, "{{nextPageToken}}", "the server provided value to retrieve the next page", true));
		$this->addFieldsToQuery($url, $this->objectDefinition->getFields());
		return $operation;
	}

	/**
	 * @param Url $url
	 * @param StaticFieldDefinition[] $fields
	 */
	private function addFieldsToQuery(Url $url, array $fields)
	{
		$sampleDate = (new DateTime())->format(DateTimeInterface::ATOM);
		$sortable = [];
		foreach ($fields as $field) {
			if (!$field->isQueryable() || $field->isCustom() || $field->isWriteOnly()) {
				continue;
			}
			if ($field->isSortable()) {
				$sortable[] = $field->getName();
			}
			if ($field->isFilterable()) {
				switch ($field->getDataType()->getName()) {
					case StringDataType::NAME:
						$url->addQuery(new Entry($field->getName(), null, null, "Optional String Filter using {$field->getName()} field", true));
						break;
					case IntegerDataType::NAME:
						if ($field->isFilterableByRange()) {
							$suffixes = ['', 'GreaterThan', 'GreaterThanOrEqualTo', 'LessThan', 'LessThanOrEqualTo'];
						} else {
							$suffixes = [''];
						}
						foreach ($suffixes as $suffix) {
							$url->addQuery(new Entry($field->getName() . $suffix, null, null, "Optional Integer Filter - filters the result by the {$field->getName()} field", true));
						}
						break;
					case BooleanDataType::NAME:
						$url->addQuery(new Entry($field->getName(), null, true, "Optional Boolean Filter using {$field->getName()} field", true));
						break;
					case DateTimeDataType::NAME:
						$suffixes = ['', 'Before', 'BeforeOrEqualTo', 'After', 'AfterOrEqualTo'];
						foreach ($suffixes as $suffix) {
							$url->addQuery(new Entry($field->getName() . $suffix, null, $sampleDate, "Optional DateTime Filter using {$field->getName()} field", true));
						}
						break;
				}
				if ($field->isNullable()) {
					$url->addQuery(new Entry($field->getName() . 'IsNull', null, false, 'Optional Filter', true));
					$url->addQuery(new Entry($field->getName() . 'IsNotNull', null, true, 'Optional Filter', true));
				}
			}
		}
		if (count($sortable) > 0) {
			$url->addQuery(new Entry("orderBy", null, "{$sortable[0]} ASC", "string - Order Results By: " . join(", ", $sortable), true));
		}
	}

	/**
	 * @param bool $read
	 * @return Operation
	 * @throws ReflectionException
	 */
	protected function generateReadOrQueryOperation(bool $read): Operation
	{
		$sampleIfModifiedSince = (new DateTime())->format(DateTimeInterface::RFC7231);
		$operation = OperationFactory::create(($read || $this->objectDefinition->isSingleton()) ? Operation::READ : Operation::QUERY, RESTClient::METHOD_GET);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$request->addHeader(new Entry("If-Modified-Since", "text", $sampleIfModifiedSince, "", true));
		$this->addPathToUrl($url, $read);
		$this->addFieldsToUrlQuery($url, $read);
		if ($this->objectDefinition->isArchivable()) {
			$url->addQuery(new Entry("deleted", null, "false", "boolean - return deleted records", true));
		}
		return $operation;
	}

	public function build(): ?Operation
	{
		return $this->generateQueryOperation();
	}
}

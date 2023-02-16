<?php

namespace Api\Objects\Postman;

use ReflectionException;
use RESTClient;
use stdClass;

class UpdateOperationBuilder extends OperationBuilder
{
	/**
	 * @throws ReflectionException
	 */
	protected function generateUpdateOperation(): Operation
	{
		$operation = OperationFactory::create(Operation::UPDATE, RESTClient::METHOD_PATCH);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addPathToUrl($url, true);
		$this->addIdVariable($url);
		$this->addFieldsToUrlQuery($url, true);
		$request->setBody($this->generateCreateAndUpdateBody(true, $this->objectDefinition->hasBinaryAttachment()));
		return $operation;
	}

	/**
	 * @param bool $forUpdate
	 * @return Body
	 */
	protected function generateCreateAndUpdateBody(bool $forUpdate, bool $hasBinaryAttachment): Body
	{
		$input = new stdClass();
		foreach ($this->objectDefinition->getFields() as $field) {
			if ($field->isReadOnly() || $field->isCustom()) {
				continue;
			}
			$name = $field->getName();
			$input->$name = $this->getValue($name, $this->objectDefinition->getType(), $field->getDataType());
		}

		$payload = json_encode($input, JSON_PRETTY_PRINT);

		$bodyMode = "raw";

		if ($hasBinaryAttachment && !$forUpdate) {
			$bodyMode = "formdata";
		}

		if ($bodyMode === "raw") {
			$body = $this->generateRawBody($payload);
		} else {
			$body = new Body();
			$body->setMode($bodyMode);
			$file = new Entry("file", "file", null, null, null, []);
			$input = new Entry("input", "text", $payload);
			$body->setFormdata([$file, $input]);
		}

		return $body;
	}

	/**
	 * @return Operation|null
	 * @throws ReflectionException
	 */
	public function build(): ?Operation
	{
		return $this->generateUpdateOperation();
	}
}

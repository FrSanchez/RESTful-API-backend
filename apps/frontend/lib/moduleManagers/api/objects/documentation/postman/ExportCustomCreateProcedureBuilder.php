<?php

namespace Api\Objects\Postman;

use Api\Actions\StaticActionDefinition;
use Api\Config\Objects\Export\ExportProcedures\BaseAllFilterByProcedure;
use Api\DataTypes\ArrayDataType;
use Api\DataTypes\DataType;
use Api\Objects\StaticObjectDefinition;
use Api\Objects\StaticObjectDefinitionCatalog;
use apiTools;
use DateTime;
use DateTimeInterface;
use generalTools;
use ReflectionException;
use RESTClient;
use stdClass;
use stringTools;
use VisitorActivityConstants;

class ExportCustomCreateProcedureBuilder extends CreateOperationBuilder
{
	private StaticActionDefinition $procedureDefinition;
	private StaticObjectDefinitionCatalog $staticObjectDefinitionCatalog;
	private StaticObjectDefinition $exportObjectDefinition;

	public function __construct(StaticActionDefinition $procedureDefinition, StaticObjectDefinition $exportDefinition, StaticObjectDefinition $exportedObjectDefinition, int $version)
	{
		$this->staticObjectDefinitionCatalog = StaticObjectDefinitionCatalog::getInstance();
		$this->procedureDefinition = $procedureDefinition;
		$this->exportObjectDefinition = $exportedObjectDefinition;
		parent::__construct($exportDefinition, $version);
	}

	/**
	 * @return array
	 */
	private function getFieldsValue(): array
	{
		$fields = [];
		$fieldDefinitions = $this->exportObjectDefinition->getFields();
		foreach ($fieldDefinitions as $fieldDefinition) {
			if (!$fieldDefinition->isWriteOnly() && (!$fieldDefinition->getDataType() instanceof ArrayDataType)) {
				$fields[] = $fieldDefinition->getName();
			}
		}
		return $fields;
	}

	/**
	 * @return stdClass
	 */
	private function getProcedureValue(): stdClass
	{
		$procedure = new stdClass();
		$procedure->name = $this->exportObjectDefinition->getType() . '/' . stringTools::camelize($this->procedureDefinition->getName());
		$procedure->arguments = new stdClass();
		foreach ($this->procedureDefinition->getArgumentNames() as $argumentName) {
			$argumentDefinition = $this->procedureDefinition->getArgumentByName($argumentName);
			$name = $argumentDefinition->getName();
			if ($this->procedureDefinition->getObject() == generalTools::EXPORT_PROCEDURE &&
				$name == BaseAllFilterByProcedure::ARG_DELETED &&
				!$this->exportObjectDefinition->isArchivable()) {
				continue;
			}
			$name = lcfirst(stringTools::camelize($name));
			$procedure->arguments->$name = $this->getValue($argumentDefinition->getName(), $argumentDefinition->getDataType()->getName(), $argumentDefinition->getDataType());
		}
		return $procedure;
	}

	public function getValue(string $name, $type, DataType $dt)
	{
		switch (strtolower($name)) {
			case 'fields':
				return $this->getFieldsValue();
			case 'procedure':
				return $this->getProcedureValue();
			case 'object':
				return $this->exportObjectDefinition->getType();
			case 'updatedafter':
			case 'createdafter':
			case 'activityafter':
			case 'updated_after':
			case 'created_after':
			case 'activity_after':
			return (new DateTime('last month'))->format(DateTimeInterface::ATOM);
			case 'updatedbefore':
			case 'createdbefore':
			case 'activitybefore':
			case 'updated_before':
			case 'created_before':
			case 'activity_before':
				return (new DateTime('tomorrow'))->format(DateTimeInterface::ATOM);
			case "deleted":
				return 'all';
			case 'type':
				return array_keys(VisitorActivityConstants::getAllActivityTypes(true, array(VisitorActivityConstants::VISITOR)));
			case 'includebyteordermark':
				return false;
			case 'maxfilesizebytes':
				return 10000000;
			default:
				return parent::getValue($name, $type, $dt);
		}
	}

	private function buildName(): string
	{
		return $this->exportObjectDefinition->getType() .
			" " .
			stringTools::camelize($this->procedureDefinition->getName());
	}
	/**
	 * @return Operation
	 * @throws ReflectionException
	 */
	protected function generateCreateOperation(): Operation
	{
		$operation = OperationFactory::create($this->buildName(), RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addPathToUrl($url);
		$this->addFieldsToUrlQuery($url, true);
		$request->setBody($this->generateCreateAndUpdateBody(false, $this->objectDefinition->hasBinaryAttachment()));
		return $operation;
	}

	/**
	 * @return Operation|null
	 * @throws ReflectionException
	 */
	public function build(): ?Operation
	{
		$className = $this->procedureDefinition->getActionClassName();
		if ($this->procedureDefinition->getObject() == generalTools::EXPORT_PROCEDURE) {
			if (!$this->exportObjectDefinition->getFieldByName($className::getRequiredObjectField())
			) {
				return null;
			}
		}
		return $this->generateCreateOperation();
	}
}

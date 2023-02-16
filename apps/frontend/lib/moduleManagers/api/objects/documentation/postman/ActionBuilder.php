<?php
namespace Api\Objects\Postman;

use Api\Actions\StaticActionDefinition;
use Api\Objects\StaticObjectDefinition;
use generalTools;
use RESTClient;
use stdClass;

class ActionBuilder extends OperationBuilder
{
	private StaticActionDefinition $actionDefinition;
	private bool $isRecordAction;

	public function __construct(StaticActionDefinition $actionDefinition, bool $isRecordAction, StaticObjectDefinition $objectDefinition, int $version)
	{
		$this->actionDefinition = $actionDefinition;
		$this->isRecordAction = $isRecordAction;
		parent::__construct($objectDefinition, $version);
	}

	public function build(): ?Operation
	{
		$actionName = generalTools::translateFromCamelCase($this->actionDefinition->getName(), " ");
		$operation = OperationFactory::create($actionName, RESTClient::METHOD_POST);
		$request = $operation->getRequest();
		$url = $request->getUrl();
		$this->addActionToUrl($url, $this->actionDefinition->getName(), $this->isRecordAction);
		$this->addIdVariable($url);
		$request->setBody($this->generateActionBody($this->actionDefinition));

		return $operation;
	}

	/**
	 * @param Url $url
	 * @param bool $useId
	 * @param string $actionName
	 */
	private function addActionToUrl(Url $url, string $actionName, bool $useId = false)
	{
		$path = $this->calculatePathForUrl($useId);
		$path[] = 'do';
		$path[] = $actionName;
		$url->setPath($path);
	}


	/**
	 * @param StaticActionDefinition $actionDefinition
	 * @return Body
	 */
	protected function generateActionBody(StaticActionDefinition $actionDefinition): Body
	{
		$input = new stdClass();
		foreach ($actionDefinition->getArgumentNames() as $argumentName) {
			$argument = $actionDefinition->getArgumentByName($argumentName);
			$name = $argument->getName();
			$input->$name = $this->getValue($name, $actionDefinition->getName(), $argument->getDataType());
		}

		$payload = json_encode($input, JSON_PRETTY_PRINT);

		return $this->generateRawBody($payload);
	}
}

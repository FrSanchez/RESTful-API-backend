<?php

namespace Api\Objects\Postman;

use JsonSerializable;
use stdClass;

class Operation implements JsonSerializable
{
	public const CREATE = 'Create';
	public const READ = 'Read';
	public const UPDATE = 'Update';
	public const DELETE = 'Delete';
	public const QUERY = 'Query';
	public const INGESTION = 'Ingestion';

	/** @var string */
	private string $name;
	/** @var Event[] */
	private array $events;
	/** @var stdClass */
	private stdClass $protocolProfileBehavior;
	/** @var Request */
	private Request $request;
	/** @var array */
	private array $response;

	public function __construct(string $name)
	{
		$this->protocolProfileBehavior = new stdClass();
		$this->response = array();
		$this->request = new Request();
		$this->request->addHeader(new Entry("Pardot-Business-Unit-Id", "text", "{{businessUnitId}}", "", false));
		$this->events = array();
		$this->setName($name);
	}

	/**
	 * @param Event|null $event
	 * @return void
	 */
	public function addEvent(?Event $event)
	{
		$this->events[] = $event;
	}

	/**
	 * @return Event[]|array|null
	 */
	public function getEvents(): array
	{
		return $this->events;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 * @return void
	 */
	public function setName(string $name)
	{
		$this->name = $name;
	}

	/**
	 * @return Request
	 */
	public function getRequest(): Request
	{
		return $this->request;
	}

	/**
	 * @param Request $request
	 * @return void
	 */
	public function setRequest(Request $request)
	{
		$this->request = $request;
	}

	/**
	 * @return array
	 */
	public function getResponse(): array
	{
		return $this->response;
	}

	/**
	 * @param array $response
	 * @return void
	 */
	public function setResponse(array $response)
	{
		$this->response = $response;
	}

	/**
	 * @return stdClass
	 */
	public function getProtocolProfileBehavior(): stdClass
	{
		return $this->protocolProfileBehavior;
	}

	/**
	 * @param stdClass $protocolProfileBehavior
	 * @return void
	 */
	public function setProtocolProfileBehavior(stdClass $protocolProfileBehavior)
	{
		$this->protocolProfileBehavior = $protocolProfileBehavior;
	}


	public function jsonSerialize()
	{
		return get_object_vars($this);
	}
}

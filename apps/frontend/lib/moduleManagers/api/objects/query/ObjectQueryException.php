<?php

namespace Api\Objects\Query;

use Api\Exceptions\ApiException;
use Throwable;

/**
 * Exception that is thrown when executing a query.
 *
 * Class ObjectQueryException
 * @package Api\Objects\Query
 */
class ObjectQueryException extends ApiException
{
	private $queryMessage;

	public function __construct(string $queryMessage, ?Throwable $previous = null)
	{
		// The queryMessage is not passed to errorDetails because errorDetails is displayed to the user and queryMessage
		// may contain details that should not be shown to the user.
		parent::__construct(-1, null, 500, $previous, []);
	}

	public function getQueryMessage(): string
	{
		return $this->queryMessage;
	}
}

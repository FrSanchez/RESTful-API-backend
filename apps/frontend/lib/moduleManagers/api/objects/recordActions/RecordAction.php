<?php
namespace Api\Objects\RecordActions;

use Api\Exceptions\ApiException;
use Api\Representations\Representation;

interface RecordAction
{
	/**
	 * Executes the action for the current record.
	 * @param RecordActionContext $recordActionContext The current context in which the record action is being executed.
	 * @param array $arguments The arguments passed to the record action. This will be an empty array if no arguments
	 * are defined for the action.
	 * @return Representation|null
	 * @throws ApiException
	 */
	public function executeAction(RecordActionContext $recordActionContext, array $arguments): ?Representation;
}

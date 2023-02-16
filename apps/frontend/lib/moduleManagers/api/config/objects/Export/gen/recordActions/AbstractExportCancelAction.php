<?php
namespace Api\Config\Objects\Export\Gen\RecordActions;

use Api\Objects\ObjectDefinition;
use Api\Objects\RecordActions\RecordActionContext;
use Api\Objects\RecordActions\RecordActionDefinition;
use Api\Gen\Representations\ExportRepresentation;
use Api\Exceptions\ApiException;
use Exception;
use DateTime;

/**
 * DO NOT MODIFY! This is generated by the API generation suite. Use "baker-api-gen" to generate a new version.

 */
abstract class AbstractExportCancelAction implements ExportCancelActionInterface
{
	/**
	 * Override this method with any validation logic. This method is executed before the {@see executeActionWithArgs}
	 * method.
	 *
	 * @param RecordActionContext $recordActionContext The current context in which the record action is being executed.
	 * @throws Exception
	 */
	public function validateWithArgs(
		RecordActionContext $recordActionContext
	): void
	{
		// override this method to validate the arguments
	}

	/**
	 * Executes the action for the current record.
	 * @param RecordActionContext $recordActionContext The current context in which the record action is being executed.
	 * @param array $arguments The arguments passed to the record action. This will be an empty array if no arguments
	 * are defined for the action.
	 * @return ExportRepresentation|null
	 * @throws ApiException
	 */
	final public function executeAction(RecordActionContext $recordActionContext, array $arguments): ?ExportRepresentation
	{
		$this->validateWithArgs(
			$recordActionContext
		);
		return $this->executeActionWithArgs(
			$recordActionContext
		);
	}

	/**
	 * @param RecordActionContext $recordActionContext The current context in which the record action is being executed.
	 * @return ExportRepresentation|null
	 */
	public abstract function executeActionWithArgs(
		RecordActionContext $recordActionContext
	): ?ExportRepresentation;
}
<?php
namespace Api\Objects\ObjectActions;

use Api\Framework\FileInput;
use Api\Representations\Representation;
use Exception;

/**
 * Implement this interface to handle the execution of an object action within the API. The common pattern is to extend
 * the abstract class associated to the action that is generated using "baker-api-gen". The abstract class adds some
 * extra functionality and delegates to abstract methods to facilitate the functionality of the action.
 *
 * To find the abstract class after running "baker-api-gen", check the "gen/objectActions" directory under the object:
 *  apps/frontend/lib/moduleManagers/api/config/objects/{object}/gen/objectActions
 */
interface ObjectAction
{
	/**
	 * Executes the object action.
	 * @param ObjectActionContext $objectActionContext The current context in which the object action is being executed.
	 * @param Representation $bodyRepresentation The input representation, which contains the arguments and values of
	 * the action being executed.
	 * @param FileInput|null $fileInput The file specified when the action was executed. This will be null if the
	 * user does not upload a file or if the action does not allow binary content.
	 * @return Representation|null
	 * @throws Exception
	 */
	public function executeObjectAction(
		ObjectActionContext $objectActionContext,
		Representation $bodyRepresentation,
		?FileInput $fileInput
	): ?Representation;
}

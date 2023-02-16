<?php
namespace Api\Representations;

use Api\Actions\StaticActionDefinition;
use Api\Actions\StaticActionDefinitionCatalog;
use generalTools;
use RuntimeException;

/**
 * {@see StaticRepresentationDefinitionCatalog} for Object Actions.
 */
class ObjectActionStaticRepresentationDefinitionCatalog implements StaticRepresentationDefinitionCatalog
{
	private StaticActionDefinitionCatalog $staticActionDefinitionCatalog;
	/** @var string[] $representationNames */
	private ?array $representationNames = null;
	private ?array $lowerNameToRepresentationInfo = null;
	/** @var StaticRepresentationDefinition[] $lowerNamesToRepresentationDefinitions */
	private array $lowerNamesToRepresentationDefinitions = [];

	public function __construct(StaticActionDefinitionCatalog $staticActionDefinitionCatalog)
	{
		$this->staticActionDefinitionCatalog = $staticActionDefinitionCatalog;
	}

	public function getRepresentationNames(): array
	{
		$this->ensureRepresentationNames();
		return $this->representationNames;
	}

	public function findRepresentationDefinitionByName(string $representationName)
	{
		$this->ensureRepresentationNames();
		if (!array_key_exists(strtolower($representationName), $this->lowerNameToRepresentationInfo)) {
			return false;
		}
		if (array_key_exists(strtolower($representationName), $this->lowerNamesToRepresentationDefinitions)) {
			return $this->lowerNamesToRepresentationDefinitions[strtolower($representationName)];
		}

		$objectActionInfo = $this->lowerNameToRepresentationInfo[strtolower($representationName)];
		$objectActionDefinition = $this->staticActionDefinitionCatalog->findStaticActionDefinitionByObjectAndName(
			$objectActionInfo['objectName'],
			$objectActionInfo['actionName']
		);

		if (!$objectActionDefinition instanceof StaticActionDefinition) {
			throw new RuntimeException("Unexpected return from finding static action definition.\nrepresentation: " . $representationName . "\nobject: " . $objectActionInfo['objectName'] . "\naction: " . $objectActionInfo['actionName']);
		}

		$properties = [];
		foreach ($objectActionDefinition->getArgumentNames() as $argumentName) {
			$argumentDefinition = $objectActionDefinition->getArgumentByName($argumentName);
			$properties[] = new RepresentationPropertyDefinition(
				$argumentDefinition->getName(),
				$argumentDefinition->getDataType(),
				true,
				true,
				$argumentDefinition->isRequired()
			);
		}

		$representationDefinition = new StaticRepresentationDefinition(
			$representationName,
			$properties,
			EmptyCustomRepresentationPropertyProvider::getInstance(),
			null
		);
		$this->lowerNamesToRepresentationDefinitions[strtolower($representationName)] = $representationDefinition;
		return $representationDefinition;
	}

	private function ensureRepresentationNames(): void
	{
		if (!is_null($this->representationNames)) {
			return;
		}
		$this->representationNames = [];
		foreach ($this->staticActionDefinitionCatalog->getObjectNamesWithActions() as $objectName) {
			$actionNames = $this->staticActionDefinitionCatalog->getActionDefinitionNamesForObject($objectName);
			foreach ($actionNames as $actionName) {
				$inputRepresentationName = self::createObjectActionInputRepresentationName($objectName, $actionName);
				$this->representationNames[] = $inputRepresentationName;
				$this->lowerNameToRepresentationInfo[strtolower($inputRepresentationName)] = [
					'objectName' => $objectName,
					'actionName' => $actionName,
				];
			}
		}
		sort($this->representationNames);
	}

	public static function createObjectActionInputRepresentationName(string $objectType, string $actionName): string
	{
		return generalTools::translateToUpperCamelCase($objectType, '_') .
			generalTools::translateToUpperCamelCase($actionName, '_') .
			'ObjectActionInputRepresentation';
	}
}

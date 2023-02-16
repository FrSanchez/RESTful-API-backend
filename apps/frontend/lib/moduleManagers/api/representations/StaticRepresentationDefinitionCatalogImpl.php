<?php
namespace Api\Representations;

/**
 * Static representation definition catalog.
 */
class StaticRepresentationDefinitionCatalogImpl implements StaticRepresentationDefinitionCatalog
{
	/** @var StaticRepresentationDefinitionCatalog[] $catalogs */
	private array $catalogs;
	/** @var StaticRepresentationDefinitionCatalog[] $lowerNameToRepresentationMap */
	private ?array $lowerNameToRepresentationMap = null;
	/** @var string[] */
	private ?array $representationNames = null;

	public function __construct(
		ObjectStaticRepresentationDefinitionCatalog $objectRepresentationDefinitionCatalog,
		YamlFileStaticRepresentationDefinitionCatalog $yamlRepresentationDefinitionCatalog,
		ObjectActionStaticRepresentationDefinitionCatalog $objectActionStaticRepresentationDefinitionCatalog
	) {
		$this->catalogs = [
			$objectRepresentationDefinitionCatalog,
			$yamlRepresentationDefinitionCatalog,
			$objectActionStaticRepresentationDefinitionCatalog
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getRepresentationNames() : array
	{
		$this->ensureRepresentationNameMap();

		return $this->representationNames;
	}

	/**
	 * @inheritDoc
	 */
	public function findRepresentationDefinitionByName(string $representationName)
	{
		$this->ensureRepresentationNameMap();
		if (!array_key_exists(strtolower($representationName), $this->lowerNameToRepresentationMap)) {
			return false;
		}

		$catalog = $this->lowerNameToRepresentationMap[strtolower($representationName)];
		return $catalog->findRepresentationDefinitionByName($representationName);
	}

	private function ensureRepresentationNameMap() : void
	{
		if (!is_null($this->lowerNameToRepresentationMap)) {
			return;
		}

		$this->representationNames = [];
		$this->lowerNameToRepresentationMap = [];

		foreach ($this->catalogs as $catalog) {
			foreach ($catalog->getRepresentationNames() as $representationName) {
				$this->representationNames[] = $representationName;
				$this->lowerNameToRepresentationMap[strtolower($representationName)] = $catalog;
			}
		}

		sort($this->representationNames);
	}
}

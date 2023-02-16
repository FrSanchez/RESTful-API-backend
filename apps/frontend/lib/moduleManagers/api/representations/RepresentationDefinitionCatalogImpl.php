<?php
namespace Api\Representations;

use AccountSettingsManagerFactory;

class RepresentationDefinitionCatalogImpl implements RepresentationDefinitionCatalog
{
	private StaticRepresentationDefinitionCatalog $staticRepresentationDefinitionCatalog;
	private AccountSettingsManagerFactory $accountSettingsManagerFactory;

	/** @var RepresentationDefinition[] */
	private array $lowerNameToRepresentationDefinitionMap = [];

	/** @var RepresentationDefinition[] */
	private array $versionToErrorRepresentationDefinitionMap = [];

	public function __construct(
		StaticRepresentationDefinitionCatalog $staticRepresentationDefinitionCatalog,
		AccountSettingsManagerFactory $accountSettingsManagerFactory
	) {
		$this->staticRepresentationDefinitionCatalog = $staticRepresentationDefinitionCatalog;
		$this->accountSettingsManagerFactory = $accountSettingsManagerFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getRepresentationNames(int $version, int $accountId) : array
	{
		return $this->staticRepresentationDefinitionCatalog->getRepresentationNames();
	}

	/**
	 * @inheritDoc
	 */
	public function getErrorRepresentationDefinition(int $version): RepresentationDefinition
	{
		$name = 'ErrorRepresentation';
		if (isset($this->versionToErrorRepresentationDefinitionMap[$version])) {
			return $this->versionToErrorRepresentationDefinitionMap[$version];
		}

		$staticRepresentationDefinition = $this->staticRepresentationDefinitionCatalog->findRepresentationDefinitionByName($name);
		if (!$staticRepresentationDefinition) {
			throw new RepresentationConfigException('Unable to find ErrorRepresentation.');
		}

		$representationDefinition = new BasicRepresentationDefinitionImpl($staticRepresentationDefinition);
		$this->versionToErrorRepresentationDefinitionMap[$version] = $representationDefinition;
		return $representationDefinition;
	}

	/**
	 * @inheritDoc
	 */
	public function findRepresentationDefinitionByName(int $version, int $accountId, string $name) : ?RepresentationDefinition
	{
		$lowerName = strtolower($name);
		if (isset($this->lowerNameToRepresentationDefinitionMap[$version][$accountId][$lowerName])) {
			return $this->lowerNameToRepresentationDefinitionMap[$version][$accountId][$lowerName];
		}

		$staticRepresentationDefinition = $this->staticRepresentationDefinitionCatalog->findRepresentationDefinitionByName($name);
		if (!$staticRepresentationDefinition) {
			return null;
		}

		$representationDefinition = new RepresentationDefinitionImpl(
			$staticRepresentationDefinition,
			$version,
			$accountId,
			$this->accountSettingsManagerFactory
		);
		$this->lowerNameToRepresentationDefinitionMap[$version][$accountId][$lowerName] = $representationDefinition;
		return $representationDefinition;
	}
}

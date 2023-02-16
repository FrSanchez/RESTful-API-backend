<?php
namespace Api\Objects;

use apiTools;
use generalTools;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

class ApiScaffolding
{
	private string $objectName;
	private string $tableName;

	public function __construct(string $objectName)
	{
		$id = apiTools::getObjectIdFromName($objectName);
		if ($id === -1) {
			throw new RuntimeException('Invalid object ' . $objectName);
		}
		$this->objectName = apiTools::getCamelCasedObjectNameFromId($id);
		$this->tableName = 'pi' . ucfirst($this->objectName) . 'Table';
	}

	/**
	 * @param bool $overwrite
	 * @param bool $create
	 * @param bool $update
	 * @param bool $delete
	 * @param bool $query
	 * @return bool
	 */
	public function generateSchemaFile(bool $overwrite, bool $create = true, bool $update = true, bool $delete = true, bool $query = true): bool
	{
		$yaml = $this->generateYaml($create, $update, $delete, $query);
		return $this->saveFile($yaml, $overwrite);
	}

	/**
	 * @param bool $create
	 * @param bool $update
	 * @param bool $delete
	 * @param bool $query
	 * @return string
	 */
	public function generateYaml(bool $create = true, bool $update = true, bool $delete = true, bool $query = true): string
	{
		$contents = $this->initContents($create, $update, $delete, $query);
		if (!class_exists($this->tableName)) {
			throw new RuntimeException('class not found ' . $this->tableName);
		}
		$table = call_user_func(array($this->tableName, 'getInstance'));

		$fields = [];
		$relationships = [];
		foreach ($table->getColumnNames() as $columnName) {
			$column = $table->getColumnDefinition($columnName);
			$fieldName = generalTools::translateToLowerCamelCase($table->getFieldName($columnName));
			if ($fieldName == 'accountId') {
				continue;
			}
			$sortable = isset($column['primary']) && $column['primary'];
			$type = $column['type'];
			if ($type == 'timestamp') {
				$type = 'datetime';
			}

			if ($fieldName == 'createdBy') {
				$relationships['createdBy'] = $this->addCreatedby();
				$fieldName = 'createdById';
			}
			if ($fieldName == 'updatedBy') {
				$relationships['updatedBy'] = $this->addUpdatedBy();
				$fieldName = 'updatedById';
			}
			if ($fieldName == 'isArchived') {
				$contents['isArchivable'] = true;
			} else {
				$fields[$fieldName] = [
					'type' => $type,
					'readOnly' => true,
				];
				if ($sortable) {
					$fields[$fieldName]['sortable'] = $sortable;
					$fields[$fieldName]['filterable'] = $sortable;
				}
			}
		}
		if (count($fields)) {
			$contents['fields'] = $fields;
		}
		if (count($relationships)) {
			$contents['relationships'] = $relationships;
		}
		return Yaml::dump($contents, 10, 4, Yaml::DUMP_OBJECT_AS_MAP);
	}

	/**
	 * @return string
	 */
	public function getObjectFolder()
	{
		$objectConfigDirectory = FileSystemStaticObjectDefinitionCatalog::getDefaultObjectConfigDirectory();
		return join(DIRECTORY_SEPARATOR, array($objectConfigDirectory, ucfirst($this->objectName)));
	}

	/**
	 * @param string $contents
	 * @param bool $overwrite
	 * @return false|int
	 */
	private function saveFile(string $contents, bool $overwrite): bool
	{
		$path = $this->getObjectFolder();
		if (!file_exists($path)) {
			if (!mkdir($path, 0777, true)) {
				return false;
			}
		}
		$schema = join(DIRECTORY_SEPARATOR, array($path, 'schema.yaml'));
		if ($overwrite || !file_exists($schema)) {
			return file_put_contents($schema, $contents);
		}
		// didn't actually write anything - thus return false
		return false;
	}

	private function initContents(bool $create, bool $update, bool $delete, bool $query)
	{
		$operations = $this->enableRead();
		if ($create) {
			$operations = array_merge($operations, $this->enableCreate());
		}
		if ($update) {
			$operations = array_merge($operations, $this->enableUpdate());
		}
		if ($delete) {
			$operations = array_merge($operations, $this->enableDelete());
		}
		if ($query) {
			$operations = array_merge($operations, $this->enableQuery());
		}

		return [
			'doctrineTable' => $this->tableName,
			'isArchivable' => false,
			'isSingleton' => false,
			'productTag' => 'INSERT PRODUCT TAG HERE',
			'operations' => $operations,
		];
	}

	private function enableCreate()
	{
		return [ 'create' => ['abilities' => '_ABILITY_REQUIRED_CREATE']];
	}

	private function enableRead()
	{
		return [ 'read' => ['abilities' => '_ABILITY_REQUIRED_READ']];
	}

	private function enableQuery()
	{
		return [ 'query' => ['abilities' => '_ABILITY_REQUIRED_QUERY']];
	}

	private function enableUpdate()
	{
		return [ 'update' => ['abilities' => '_ABILITY_REQUIRED_UPDATE']];
	}

	private function enableDelete()
	{
		return [ 'delete' => ['abilities' => '_ABILITY_REQUIRED_DELETE']];
	}

	private function addCreatedBy()
	{
		return $this->addCreatedOrUpdatedBy('piCreated', 'created_by');
	}

	private function addUpdatedBy()
	{
		return $this->addCreatedOrUpdatedBy('piUpdated', 'updated_by');
	}

	private function addCreatedOrUpdatedBy($doctrineName, $doctrineField)
	{
		return [
			'doctrineName' => $doctrineName,
			'referenceTo' => ['object' => 'User', 'key' => 'id'],
			'doctrineField' => $doctrineField,
		];
	}
}

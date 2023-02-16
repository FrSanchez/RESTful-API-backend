<?php

namespace Api\Objects\Postman;

use Api\Objects\Collections\ObjectItemTypeDefinition;
use Api\Objects\FileSystemStaticObjectDefinitionCatalog;
use Api\Objects\StaticObjectDefinition;
use ReflectionException;

class ReadOperationBuilder extends QueryOperationBuilder
{
	/**
	 * @throws ReflectionException
	 */
	protected function generateReadOperation(): Operation
	{
		$operation = $this->generateReadOrQueryOperation(!$this->objectDefinition->isSingleton());
		$url = $operation->getRequest()->getUrl();
		if (!!$this->objectDefinition->isSingleton()) {
			$this->addIdVariable($url);
		}
		$this->addRelationshipsToRead($url);
		$this->addCollectionsToRead($url, $this->objectDefinition);
		return $operation;
	}

	private function addRelationshipsToRead(Url $url)
	{
		$fields = [];
		foreach ($this->objectDefinition->getRelationshipNames() as $relationship) {
			$relationshipDefinition = $this->objectDefinition->getRelationshipByName($relationship);
			$name = $relationshipDefinition->getName();
			$relatedObjectDefinition = FileSystemStaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($relationshipDefinition->getReferenceToDefinition()->getObjectName());
			foreach ($relatedObjectDefinition->getFields() as $field) {
				$fieldName = $field->getName();
				if ($fieldName != 'replyToOptions' && $fieldName != 'senderOptions') {
					$fields[] = "$name.{$fieldName}";
				}
			}
		}
		$this->setFieldsToUrl($url, $fields);
	}

	/**
	 * @param Url $url
	 * @param StaticObjectDefinition $objectDefinition
	 */
	private function addCollectionsToRead(Url $url, StaticObjectDefinition $objectDefinition)
	{
		$fields = [];
		foreach ($objectDefinition->getCollectionNames() as $collection) {
			$collectionDefinition = $objectDefinition->getCollectionDefinitionByName($collection);
			$name = $collectionDefinition->getName();
			$type = $collectionDefinition->getItemType();
			if ($type instanceof ObjectItemTypeDefinition) {
				$relatedObjectDefinition = FileSystemStaticObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($type->getObjectType());
				foreach ($relatedObjectDefinition->getFields() as $field) {
					$fields[] = "$name.{$field->getName()}";
				}
			}
		}
		$this->setFieldsToUrl($url, $fields);
	}

	/**
	 * @return Operation|null
	 * @throws ReflectionException
	 */
	public function build(): ?Operation
	{
		return $this->generateReadOperation();
	}
}

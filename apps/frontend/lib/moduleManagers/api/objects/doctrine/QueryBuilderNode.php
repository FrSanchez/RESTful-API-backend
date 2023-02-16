<?php
namespace Api\Objects\Doctrine;

use Doctrine_Query;

/**
 * Fills a query with selected fields and relationship traversals.
 *
 * Doctrine will throw an error if multiple relationships are added with the same name. This class ensures that fields
 * and relationships are always specified only once even when the fields and relationships are passed multiple times.
 * This allows for multiple functions to add selected fields (along with relationships) without having to worry about
 * what was added previously (or how it was added previously).
 *
 * Class QueryBuilderNode
 * @package Api\Objects\Doctrine
 */
class QueryBuilderNode
{
	/** @var string[] $fields */
	private $fields = [];

	/** @var QueryBuilderNode[] $relationships */
	private $relationships = [];

	/** @var QueryBuilderJoinCriteria */
	private $joinCriteria;

	/**
	 * @param string $doctrineName
	 * @return QueryBuilderNode
	 */
	public function getRelationshipQueryBuilder(string $doctrineName): QueryBuilderNode
	{
		if (!array_key_exists($doctrineName, $this->relationships)) {
			throw new \RuntimeException(
				"Unable to find the requested relationship: $doctrineName"
			);
		}
		return $this->relationships[$doctrineName];
	}

	/**
	 * @param string $relationshipName
	 * @param QueryBuilderJoinCriteria $joinWithCriteria A criteria object which will be combined with the default
	 *   join criteria using doctrine's WITH feature.
	 * @return QueryBuilderNode
	 */
	public function addRelationship(string $relationshipName, QueryBuilderJoinCriteria $joinWithCriteria = null): QueryBuilderNode
	{
		if (!array_key_exists($relationshipName, $this->relationships)) {
			$this->relationships[$relationshipName] = new QueryBuilderNode();
			$this->relationships[$relationshipName]->setJoinCriteria($joinWithCriteria);
		}

		return $this;
	}

	/**
	 * @param string ...$selection
	 * @return QueryBuilderNode Returns this for fluent style programming
	 */
	public function addSelection(... $selection): QueryBuilderNode
	{
		if (count($selection) == 1) {
			$field = array_shift($selection);
			$this->fields[$field] = true;
			return $this;
		}

		$relationshipName = array_shift($selection);
		if (!array_key_exists($relationshipName, $this->relationships)) {
			$this->relationships[$relationshipName] = new QueryBuilderNode();
		}
		$this->relationships[$relationshipName]->addSelection(...$selection);
		return $this;
	}

	private function setJoinCriteria($joinCriteria)
	{
		$this->joinCriteria = $joinCriteria;
		return $this;
	}

	/**
	 * @param Doctrine_Query $doctrineQuery
	 * @param string $alias
	 * @param string[] $takenAliases
	 */
	public function applyToDoctrineQuery(Doctrine_Query $doctrineQuery, string $alias, array $takenAliases = [])
	{
		foreach (array_keys($this->fields) as $field) {
			$doctrineQuery->addSelect("{$alias}.{$field}");
		}

		foreach ($this->relationships as $relationshipName => $relationshipNode) {
			// calculate an alias for this relationship and make sure it's not a duplicate of another alias
			$relationshipAlias = $this->generateRelationshipAlias($relationshipName, $alias, $takenAliases);
			$takenAliases[$relationshipAlias] = true;

			// add the relationship as a left join into the query
			$leftJoin = "{$alias}.{$relationshipName} {$relationshipAlias}";
			if ($relationshipNode->joinCriteria) {
				$leftJoin .= " WITH " . $relationshipNode->joinCriteria->buildClause($alias, $relationshipAlias);
			}
			$doctrineQuery->leftJoin($leftJoin);

			// add the relationship node
			$relationshipNode->applyToDoctrineQuery($doctrineQuery, $relationshipAlias, $takenAliases);
		}
	}

	/**
	 * Creates a new alias for a relationship.
	 * @param string $relationshipName
	 * @param string $parentAlias
	 * @param string[] $takenAliases
	 * @return string
	 */
	private function generateRelationshipAlias(string $relationshipName, string $parentAlias, array $takenAliases): string
	{
		// remove the "pi" if the relationship starts with it
		if (\stringTools::startsWith($relationshipName, 'pi')) {
			$relationshipName = substr($relationshipName, 2);
		}

		// attempt to create an alias based on the first character. if it's taken, then create an alias
		// based on the first two characters, and then try three and so on.
		// so "Email" with parent of "v" will be "vE" if it's not already taken, then "vEm", "vEma"...
		$foundAlias = false;
		for ($index = 1; $index < strlen($relationshipName); $index++) {
			$possibleAlias = strtolower($parentAlias . substr($relationshipName, 0, $index));
			if (!array_key_exists($possibleAlias, $takenAliases)) {
				$foundAlias = $possibleAlias;
				break;
			}
		}

		// we weren't able to find a unique name for some reason so we are now going to try and calculate based on appending
		// an index (like "email0"). We will try up to 1000 values and then fail if we still can't generate an alias.
		// The 1000 is just an arbitrary upper limit and assumes we will never have 1000 tables within a SELECT statement.
		if (!$foundAlias) {
			$firstChar = substr($relationshipName, 0, 1);
			for ($index = 0; $index < 1000; $index++) {
				$possibleAlias = strtolower($parentAlias . $firstChar . $index);
				if (!array_key_exists($possibleAlias, $takenAliases)) {
					$foundAlias = $possibleAlias;
					break;
				}
			}
		}

		if (!$foundAlias) {
			throw new \RuntimeException("Unable to generate a unique alias for relationship: {$parentAlias}.{$relationshipName}");
		}

		return $foundAlias;
	}
}

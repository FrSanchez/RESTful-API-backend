<?php
namespace Api\Objects\Doctrine;

interface QueryBuilderJoinCriteria
{
	/*
	 * As of October 2020, we have only implemented QueryBuilderJoinCriteriaRelatedFieldEqualsConstant.
	 * As other types of criteria are needed, they can be added.  This includes boolean logic criteria.
	 * (QueryBuilderNode should not be extended to take an array of criteria objects.  Instead we should
	 * implement something like QueryBuilderJoinCriteriaAll and QueryBuilderJoinCriteriaAny and have those
	 * take child criteria.  In this way, we can support arbitrarily complex join criteria.)
	 *
	 * Also, as of October 2020, we only ever use QueryBuilderJoinCriteria using a doctrine WITH clause on a
	 * left join.  This means that doctrine applies the implied join criteria from the relationship and combines
	 * it with the clause from the QueryBuilderJoinCriteria derived object using an AND (effectively letting you
	 * exclude data that would have been joined in based on just the relationship).  If, in the future, we
	 * allow ON clauses instead of WITH, objects derived from QueryBuilderJoinCriteria should still work.  Only
	 * the way they are passed to QueryBuilderNode would change (currently done using
	 * QueryBuilderNode::addRelationship).
	 */
	public function buildClause(string $primaryAlias, $relationshipAlias);
}

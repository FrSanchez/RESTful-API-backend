<?php
namespace Api\Config\Objects\VisitorActivity;

use Api\Config\Objects\VisitorActivity\Gen\Doctrine\AbstractVisitorActivityDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use Api\Objects\Query\QueryContext;
use Doctrine_Query;

/**
 * Override for the VisitorActivity integration for Doctrine
 * Class VisitorActivityDoctrineQueryModifier
 * @package Api\Objects\Doctrine
 */
class VisitorActivityDoctrineQueryModifier extends AbstractVisitorActivityDoctrineQueryModifier
{
	public function createDoctrineQuery(QueryContext $queryContext, array $selections): Doctrine_Query
	{
		$query = parent::createDoctrineQuery($queryContext, $selections);
		$query->andWhere('is_filtered = 0');
		return $query;
	}

	protected function modifyQueryWithDetailsField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDefinition)
	{
		$queryBuilderRoot
			->addSelection('type')
			->addSelection('account_id')
			->addSelection('connector_activity_id')
			->addSelection('custom_url_id')
			->addSelection('email_id')
			->addSelection('filex_id')
			->addSelection('form_id')
			->addSelection('form_handler_id')
			->addSelection('landing_page_id')
			->addSelection('multivariate_test_variation_id')
			->addSelection('paid_search_ad_id')
			->addSelection('social_message_link_id')
			->addSelection('tracker_id')
			->addSelection('visit_id')
			->addSelection('visitor_id')
			->addSelection('visitor_page_view_id');

		$queryBuilderRoot
			->addSelection('piEmail', 'id')
			->addSelection('piEmail', 'name')
			->addSelection('piEmail', 'list_email_id')
			->addSelection('piEmail', 'email_message_id')
			->addSelection('piEmail', 'piListEmail', 'id')
			->addSelection('piEmail', 'piEmailMessage', 'email_template_id')
			->addSelection('piEmail', 'piEmailMessage', 'subject')
			->addSelection('piEmail', 'piEmailMessage', 'piEmailTemplate', 'id')
			->addSelection('piForm', 'id')
			->addSelection('piForm', 'name')
			->addSelection('piFormHandler', 'id')
			->addSelection('piFormHandler', 'name')
			->addSelection('piProspect', 'id')
			->addSelection('piProspect', 'salutation')
			->addSelection('piProspect', 'first_name')
			->addSelection('piProspect', 'last_name')
			->addSelection('piProspect', 'crm_contact_fid')
			->addSelection('piProspect', 'crm_lead_fid')
			->addSelection('piPaidSearchAd', 'id')
			->addSelection('piPaidSearchAd', 'headline')
			->addSelection('piSiteSearchQuery', 'id')
			->addSelection('piSiteSearchQuery', 'query')
			->addSelection('piSiteSearchQuery', 'site_search_id')
			->addSelection('piTracker', 'id')
			->addSelection('piTracker', 'email_id')
			->addSelection('piTracker', 'tracker_redirect_id')
			->addSelection('piTracker', 'landing_page_id')
			->addSelection('piTracker', 'piEmail', 'id')
			->addSelection('piTracker', 'piEmail', 'email_message_id')
			->addSelection('piTracker', 'piEmail', 'piEmailMessage', 'id')
			->addSelection('piTracker', 'piEmail', 'piEmailMessage', 'subject')
			->addSelection('piTracker', 'piTrackerRedirect', 'id')
			->addSelection('piTracker', 'piTrackerRedirect', 'redirect_location')
			->addSelection('piLandingPage', 'id')
			->addSelection('piLandingPage', 'name')
			->addSelection('piVisitorPageView', 'id')
			->addSelection('piVisitorPageView', 'title')
			->addSelection('piMultivariateTestVariation', 'id')
			->addSelection('piMultivariateTestVariation', 'landing_page_id')
			->addSelection('piFilex', 'id')
			->addSelection('piFilex', 'name')
			->addSelection('piConnectorActivity', 'id')
			->addSelection('piConnectorActivity', 'object_id')
			->addSelection('piConnectorActivity', 'account_id')
			->addSelection('piCustomUrl', 'id')
			->addSelection('piCustomUrl', 'name')
			->addSelection('piSocialMessageLink', 'id')
			->addSelection('piSocialMessageLink', 'dest_url');
	}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForDetailsField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piVisitorActivity $doctrineRecord */
		return $doctrineRecord->getAssociatedObjectName(\piVisitorActivity::MAX_NAME_LENGTH);
	}

	protected function modifyQueryWithTypeNameField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDefinition)
	{
		$queryBuilderRoot
			->addSelection('email_id')
			->addSelection('filex_id')
			->addSelection('form_id')
			->addSelection('form_handler_id')
			->addSelection('landing_page_id')
			->addSelection('multivariate_test_variation_id')
			->addSelection('paid_search_ad_id')
			->addSelection('piEmail', 'id')
			->addSelection('piFilex', 'id')
			->addSelection('piForm', 'id')
			->addSelection('piFormHandler', 'id')
			->addSelection('piLandingPage', 'id')
			->addSelection('piPaidSearchAd', 'id')
			->addSelection('piMultivariateTestVariation', 'id')
			->addSelection('piMultivariateTestVariation', 'landing_page_id')
			->addSelection('piMultivariateTestVariation', 'piLandingPage', 'id')
			->addSelection('piSiteSearchQuery', 'id')
			->addSelection('piTracker', 'id')
			->addSelection('piVisitor', 'id')
			->addSelection('piVisitorPageView', 'id')
			->addSelection('piVisit', 'id')
			->addSelection('type')
			->addSelection('site_search_query_id')
			->addSelection('tracker_id')
			->addSelection('visit_id')
			->addSelection('visitor_id')
			->addSelection('visitor_page_view_id');
	}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForTypeNameField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piVisitorActivity $doctrineRecord */
		return $doctrineRecord->getActivityName();
	}

	protected function modifyQueryWithListEmailIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDefinition)
	{
		$queryBuilderRoot
			->addSelection('email_id')
			->addSelection('piEmail', 'id')
			->addSelection('piEmail', 'list_email_id');
	}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForListEmailIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piVisitorActivity $doctrineRecord */
		return $doctrineRecord->piEmail->list_email_id ?? null;
	}

	protected function modifyQueryWithEmailTemplateIdField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDefinition)
	{
		$queryBuilderRoot
			->addSelection('email_id')
			->addSelection('piEmail', 'id')
			->addSelection('piEmail', 'email_message_id')
			->addSelection('piEmail', 'piEmailMessage', 'email_template_id')
			->addSelection('piEmail', 'piEmailMessage', 'piEmailTemplate', 'id');
	}

	/**
	 * @param \Doctrine_Record $doctrineRecord
	 * @param FieldDefinition $fieldDef
	 * @return mixed
	 */
	protected function getValueForEmailTemplateIdField(\Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var \piVisitorActivity $doctrineRecord */
		return $doctrineRecord->getEmailTemplateId();
	}
}

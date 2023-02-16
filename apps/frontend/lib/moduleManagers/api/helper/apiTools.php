<?php

class apiTools
{
	const MIME_TYPE_APPLICATION_JSON = 'application/json';
	const MIME_TYPE_MULTIPART_FORM_DATA = 'multipart/form-data';
	const UNKNOWN_OBJECT = 'unknownObject';  // Note getObjectNameFromId will change this to "UnknownObject"

	/**
	 * @param \piWebRequest $request
	 * @return bool
	 */
	public static function isJsonMimeType(\piWebRequest $request): bool
	{
		return self::getMimeTypeFromContentType($request) == self::MIME_TYPE_APPLICATION_JSON;
	}

	/**
	 * @param \piWebRequest $request
	 * @return bool
	 */
	public static function isMultipartFormMimeType(\piWebRequest $request): bool
	{
		return self::getMimeTypeFromContentType($request) == self::MIME_TYPE_MULTIPART_FORM_DATA;
	}

	/**
	 * Gets the MIME type from the Content-Type header on the request. If there is no Content-Type header, then a null
	 * is returned.
	 *
	 * Content-Type can be in one of two formats
	 *   * application/json
	 *   * application/json; charset=utf-8
	 *
	 * @param \piWebRequest $request
	 * @return string|null
	 */
	public static function getMimeTypeFromContentType(\piWebRequest $request)
	{
		$contentType = $request->getContentType();
		$mimeType = $contentType;
		if ($index = stripos($contentType, ';')) {
			$mimeType = substr($contentType, 0, $index);
		}
		return $mimeType;
	}


	/**
	 * @param $objectName
	 * @return int
	 */
	public static function getObjectIdFromName(string $objectName): int
	{
		$objectName = strtolower(generalTools::translateToLowerCamelCase($objectName));
		switch ($objectName) {
			case 'email':
				return generalTools::EMAIL;
			case 'landingpage':
				return generalTools::LANDING_PAGE;
			case 'form':
				return generalTools::FORM;
			case 'user':
				return generalTools::USER;
			case 'visitor':
				return generalTools::VISITOR;
			case 'prospect':
				return generalTools::PROSPECT;
			case 'prospectaccount':
				return generalTools::PROSPECT_ACCOUNT;
			case 'formhandler':
				return generalTools::FORM_HANDLER;
			case 'list':
				return generalTools::LISTX;
			case 'profile':
				return generalTools::PROFILE;
			case 'automationrules':
				return generalTools::AUTOMATION_RULES;
			case 'opportunity':
				return generalTools::OPPORTUNITY;
			case 'campaign':
				return generalTools::CAMPAIGN;
			case 'dynamiccontent':
				return generalTools::DYNAMIC_CONTENT;
			case 'customfield':
				return generalTools::PROSPECT_FIELD_CUSTOM;
			case 'customredirect':
				return generalTools::CUSTOM_URL;
			case 'emailtemplate':
				return generalTools::EMAIL_TEMPLATE;
			case 'lifecyclehistory':
				return generalTools::LIFECYCLE_STAGE_LOG;
			case 'lifecyclestage':
				return generalTools::LIFECYCLE_STAGE;
			case 'listmembership':
				return generalTools::LISTX_PROSPECT;
			case 'tag':
				return generalTools::TAG;
			case 'tagobject':
				return generalTools::TAG_OBJECT;
			case 'visitoractivity':
				return generalTools::VISITOR_ACTIVITY;
			case 'visit':
				return generalTools::VISIT;
			case 'scoringcategory':
				return generalTools::SCORING_CATEGORY;
			case 'prospectscoringcategoryscore':
				return generalTools::PROSPECT_SCORING_CATEGORY_SCORE;
			case 'emailclick':
				return generalTools::EMAIL_CLICK;
			case 'account':
				return generalTools::ACCOUNT;
			case 'folder':
				return generalTools::FOLDER;
			case 'layouttemplate':
				return generalTools::LAYOUT_TEMPLATE;
			case 'microcampaign':
				return generalTools::MICRO_CAMPAIGN;
			case 'opportunityprospect':
				return generalTools::OPPORTUNITY_PROSPECT;
			case 'prospectcustomfield':
				return generalTools::PROSPECT_CUSTOM_FIELD;
			case 'trackedemail':
				return generalTools::TRACKED_EMAIL;
			case 'variabletags':
				return generalTools::VARIABLE_TAGS;
			case 'trackedlink':
				return generalTools::TRACKED_LINK;
			case 'file':
				return generalTools::FILE;
			case 'import':
				return generalTools::IMPORT;
			case 'export':
				return generalTools::EXPORT;
			case 'trackerdomain':
				return generalTools::TRACKER_DOMAIN;
			case 'lifecyclestageprospect':
				return generalTools::LIFECYCLE_STAGE_PROSPECT;
			case 'taggedobject':
				return generalTools::TAG_OBJECT;
			case 'video':
				return generalTools::VIDEO;
			case 'multivariatetestvariation':
				return generalTools::MULTIVARIATE_TEST_VARIATION;
			case 'visitorpageview':
				return generalTools::VISITOR_PAGE_VIEW;
			case 'listemail':
				return generalTools::LIST_EMAIL;
			case 'formhandlerfield':
				return generalTools::FORM_HANDLER_FORM_FIELD;
			case 'externalactivity':
				return generalTools::EXTERNAL_ACTIVITY;
			case 'dynamiccontentvariation':
				return generalTools::DYNAMIC_CONTENT_VARIATION;
			case 'pageaction':
				return generalTools::PAGE_ACTION;
			case 'salesforce':
				return generalTools::SALESFORCE_CONNECTOR;
			case 'exportprocedure':
				return generalTools::EXPORT_PROCEDURE;
			case 'engagementprogram':
				return generalTools::ENGAGEMENT_PROGRAM;
			case 'bulkaction':
				return generalTools::API_BULK_ACTION;
			default:
				PardotLogger::getInstance()->log(PardotLogger::INFO, "Unable to determine api object being used: " . $objectName);
				return -1;
		}
	}

	/**
	 * @param int $objectId
	 * @return string
	 */
	public static function getObjectNameFromId(int $objectId): string
	{
		// We generally want to represent object type names using pascal case and not camel case. We derive one
		// from the other to avoid having two hard coded lists. It would be even better if there were zero hard
		// coded lists and these names came from the directory names containing schema.yaml files.
		$camelCasedName = self::getCamelCasedObjectNameFromId($objectId);
		// It would be better if we returned NULL when the ID doesn't map to a known object, but not changing at this time.
		return is_null($camelCasedName) ? ucfirst(self::UNKNOWN_OBJECT) : ucfirst($camelCasedName);
	}

	/**
	 * @param int $objectId
	 * @return string
	 */
	public static function getCamelCasedObjectNameFromId(int $objectId): string
	{
		switch ($objectId) {
			case generalTools::EMAIL:
				return 'email';
			case generalTools::LANDING_PAGE:
				return 'landingPage';
			case generalTools::FORM:
				return 'form';
			case generalTools::USER:
				return 'user';
			case generalTools::VISITOR:
				return 'visitor';
			case generalTools::PROSPECT:
				return 'prospect';
			case generalTools::PROSPECT_ACCOUNT:
				return 'prospectAccount';
			case generalTools::FORM_HANDLER:
				return 'formHandler';
			case generalTools::LISTX:
				return 'list';
			case generalTools::PROFILE:
				return 'profile';
			case generalTools::AUTOMATION_RULES:
				return 'automationRules';
			case generalTools::OPPORTUNITY:
				return 'opportunity';
			case generalTools::CAMPAIGN:
				return 'campaign';
			case generalTools::DYNAMIC_CONTENT:
				return 'dynamicContent';
			case generalTools::DYNAMIC_CONTENT_VARIATION:
				return 'dynamicContentVariation';
			case generalTools::PROSPECT_FIELD_CUSTOM:
				return 'customField';
			case generalTools::CUSTOM_URL:
				return 'customRedirect';
			case generalTools::EMAIL_TEMPLATE:
				return 'emailTemplate';
			case generalTools::LIFECYCLE_STAGE_LOG:
				return 'lifecycleHistory';
			case generalTools::LIFECYCLE_STAGE:
				return 'lifecycleStage';
			case generalTools::LISTX_PROSPECT:
				return 'listMembership';
			case generalTools::TAG:
				return 'tag';
			case generalTools::TAG_OBJECT:
				return 'taggedObject';
			case generalTools::VISITOR_ACTIVITY:
				return 'visitorActivity';
			case generalTools::VISIT:
				return 'visit';
			case generalTools::SCORING_CATEGORY:
				return 'scoringCategory';
			case generalTools::PROSPECT_SCORING_CATEGORY_SCORE:
				return 'prospectScoringCategoryScore';
			case generalTools::EMAIL_CLICK:
				return 'emailClick';
			case generalTools::ACCOUNT:
				return 'account';
			case generalTools::FOLDER:
				return 'folder';
			case generalTools::LAYOUT_TEMPLATE:
				return 'layoutTemplate';
			case generalTools::MICRO_CAMPAIGN:
				return 'microCampaign';
			case generalTools::OPPORTUNITY_PROSPECT:
				return 'opportunityProspect';
			case generalTools::PROSPECT_CUSTOM_FIELD:
				return 'prospectCustomField';
			case generalTools::SFDC_CONNECTOR:
				return 'sfdcConnector';
			case generalTools::TRACKED_EMAIL:
				return 'trackedEmail';
			case generalTools::VARIABLE_TAGS:
				return 'variableTags';
			case generalTools::TRACKED_LINK:
				return 'trackedlink';
			case generalTools::FILE:
				return 'file';
			case generalTools::NURTURE:
				return 'listEngagementStudioProgram';
			case generalTools::IMPORT:
				return 'import';
			case generalTools::EXPORT:
				return 'export';
			case generalTools::LIFECYCLE_STAGE_PROSPECT:
				return 'lifecycleStageProspect';
			case generalTools::TRACKER_DOMAIN:
				return 'trackerDomain';
			case generalTools::VIDEO:
				return 'video';
			case generalTools::MULTIVARIATE_TEST_VARIATION:
				return 'multivariateTestVariation';
			case generalTools::VISITOR_PAGE_VIEW:
				return 'visitorPageView';
			case generalTools::LIST_EMAIL:
				return 'listEmail';
			case generalTools::FORM_HANDLER_FORM_FIELD:
				return 'formHandlerField';
			case generalTools::EXTERNAL_ACTIVITY:
				return 'externalActivity';
			case generalTools::PAGE_ACTION:
				return 'pageAction';
			case generalTools::SALESFORCE_CONNECTOR:
				return 'connector';
			case generalTools::ENGAGEMENT_PROGRAM:
				return 'engagementProgram';
			case generalTools::API_BULK_ACTION:
				return 'bulkAction';
			default:
				PardotLogger::getInstance()->log(PardotLogger::INFO, "Unable to determine object id being used: " . $objectId);
				// It would be better if we returned NULL when the ID doesn't map to a known object, but not changing at this time.
				return self::UNKNOWN_OBJECT;
		}
	}

	/**
	 * @param string $objectName
	 * @param bool $isSingleton
	 * @return string
	 */
	public static function generateUrlNameFromObjectName(string $objectName, bool $isSingleton): string
	{
		$objectUrlName = $objectName;

		// Only pluralize if the object is not a singleton
		if (!$isSingleton){
			$objectUrlName = generalTools::pluralize($objectUrlName);
		}

		// multi-word objects should be joined by hyphens, convert to snake case and replace underscores with hyphens
		$urlName = str_replace('_', '-', stringTools::snakeFromCamelCase($objectUrlName));

		return strtolower($urlName);
	}

	/**
	 * @param int $accountId
	 * @param int $objectType
	 * @return mixed
	 */
	public static function getDefaultFolderId( int $accountId, int $objectType)
	{
		$folderId = false;
		$folderManager = new FolderManager();
		/** @var piFolder $defaultFolder */
		$defaultFolder = $folderManager->getDefaultFolderForType($accountId, $objectType);
		if ($defaultFolder instanceof piFolder) {
			$folderId = $defaultFolder->id;
		}
		return $folderId;
	}

	/**
	 * Extracts the module name from an object type returned from an ORM
	 *
	 * Returns the given string if expected formatting is not matched
	 *
	 * @param string $table
	 * @return string
	 */
	public static function getModuleNameFromTable(string $table): string
	{
		$module = $table;

		if (preg_match('/^pi/', $table)) {
			$module = substr($table, 2); // Remove 'pi'
		}

		if (substr($module, -1) == 'x') { // Remove ending 'x'
			$module = substr($module, 0, -1);
		}

		$objectConstant = apiTools::getObjectIdFromName($module);
		if ($objectConstant == -1) {
			return ucfirst($module);
		}

		return ucfirst(apiTools::getCamelCasedObjectNameFromId($objectConstant));
	}
}

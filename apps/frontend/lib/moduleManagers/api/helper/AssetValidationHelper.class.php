<?php

/**
 * Class AssetValidationHelper
 *
 * This class is for helping validate common assets like forms, layout templates, campaigns, and
 * especially folders that are used across different API managers to make sure that the asset exists
 * and that the API user has permission to use those assets
 *
 */
class AssetValidationHelper
{
	private $accountId;
	/** @var $apiActions apiActions */
	private $apiActions;

	/**
	 * AssetValidationHelper constructor.
	 * @param $apiActions
	 */
	public function __construct($apiActions)
	{
		$this->apiActions = $apiActions;
		$this->accountId = $apiActions->apiUser ? $apiActions->apiUser->account_id : null;
	}

	/**
	 * Creates a map of an asset's id as key like form id or landing page id with the value
	 * being the piFolder object so we can bulk load these for query calls to do quick folder
	 * permission validations
	 *
	 * @param $objectIds
	 * @param $objectType
	 * @return array
	 */
	public function createObjectIdFolderMap($objectIds, $objectType)
	{
		$objectFolderMap = [];
		$folderObjects =
			piFolderObjectTable::getInstance()->findByObjectTypeAndIds($this->accountId, $objectType, $objectIds);

		foreach ($folderObjects as $folderObject) {
			$objectFolderMap[$folderObject->object_id] = $folderObject->piFolder;
		}

		return $objectFolderMap;
	}

	/**
	 * Given a piFolder, check if API user has permission to enter this folder
	 *
	 * @param piFolder $folder
	 * @return bool
	 */
	public function validateFolderAccess(piFolder $folder)
	{
		$accessManager = new AccessManager();
		return $accessManager->hasAccessToFolder($folder, $this->apiActions->apiUser);
	}


	/**
	 * Given a folder ID, check if folder exists and if API user has permission to enter folder
	 *
	 * @param $folderId
	 * @return Doctrine_Collection|piFolder|piFolder[]
	 */
	public function validateFolder($folderId)
	{
		$folder = piFolderTable::getInstance()->retrieveByIds($this->accountId,
			$folderId);

		// no folder id or invalid folder id provided in API call
		if (!$folder) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INVALID_FOLDER_ID);
		}

		// folder id is valid, but user doesn't have folder access
		if (!$this->validateFolderAccess($folder)) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INSUFFICIENT_FOLDER_ACCESS);
		}

		return $folder;
	}

	/**
	 * Given layout template ID, verify layout template exists and that API user has permission
	 * to the folder that the layout template is in
	 *
	 * @param $layoutTemplateId
	 * @return piLayoutTemplate
	 */
	public function validateLayoutTemplate($layoutTemplateId)
	{
		$layoutTemplate = piLayoutTemplateTable::getInstance()->retrieveByIds($layoutTemplateId, $this->accountId);
		if(!$layoutTemplate) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INVALID_TEMPLATE);
		}

		$layoutTemplateFolderObj = piFolderObjectTable::getInstance()->retrieveForObject($this->accountId,
			$layoutTemplateId, generalTools::LAYOUT_TEMPLATE);

		// folder id is valid, but user doesn't have folder access
		if (!$this->validateFolderAccess($layoutTemplateFolderObj->piFolder)) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INSUFFICIENT_FOLDER_ACCESS);
		}

		return $layoutTemplate;
	}

	/**
	 * Given layout template ID, verify layout template exists and that API user has permission
	 * to the folder that the layout template is in
	 *
	 * @param $campaignId
	 * @return piCampaign
	 */
	public function validateCampaign($campaignId)
	{
		$campaign = piCampaignTable::getInstance()->retrieveByIds($campaignId, $this->accountId);
		if(!$campaign) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INVALID_CAMPAIGN_ID);
		}

		$campaignFolderObj = piFolderObjectTable::getInstance()->retrieveForObject($this->accountId,
			$campaignId, generalTools::CAMPAIGN);

		// folder id is valid, but user doesn't have folder access
		if (!$this->validateFolderAccess($campaignFolderObj->piFolder)) {
			$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INSUFFICIENT_FOLDER_ACCESS);
		}

		return $campaign;
	}

	/**
	 * returns API_ERROR_INSUFFICIENT_FOLDER_ACCESS permission to client if insufficient folder permissions
	 * @param $objectId
	 * @param $objectType
	 */
	public function validateFolderAccessForObject($objectId, $objectType)
	{
		$objectFolder = piFolderObjectTable::getInstance()->retrieveForObject($this->accountId,
			$objectId, $objectType);

		$apiFolderPermissionEnforcementFF = AccountSettingsManager::accountHasFeatureEnabled($this->accountId, AccountSettingsConstants::FEATURE_API_FOLDER_PERMISSION_ENFORCEMENT);

		if ($objectFolder && !$this->validateFolderAccess($objectFolder->piFolder)) {

			if($apiFolderPermissionEnforcementFF) {
				$this->apiActions->doErrorForward(ApiErrorLibrary::API_ERROR_INSUFFICIENT_FOLDER_ACCESS);
			} else {
				PardotLogger::getInstance()->info(
					json_encode([
						'W-5494870' => 'permission validation failed',
						'account_id' => $this->accountId,
						'user_id' => $this->apiActions->apiUser->id,
						'api_module' => $this->apiActions->module,
						'api_action' => $this->apiActions->action,
						'asset_id' => $objectId,
						'folder_id' => $objectFolder->piFolder->id
					])
				);
			}
		}
	}

	/**
	 * Logs objects that don't respect folder permissions
	 *
	 * @param Doctrine_Collection $objects
	 * @param int $objectType type of object. e.g. generalTools::FORM
	 * @return array object ids that user doesn't have permissions
	 */
	public function logObjectsThatDontRespectFolderPermissions($objects, $objectType)
	{

		$objectIds = [];
		foreach($objects as $object) {
			$objectIds[] = $object->id;
		}

		$invalidObjectIds = [];
		$lockedOutFolderIds = [];
		$objectIdFolderMap = $this->createObjectIdFolderMap($objectIds, $objectType);
		foreach ($objects as $object) {
			$objectFolder = key_exists($object->id, $objectIdFolderMap) ? $objectIdFolderMap[$object->id] : null;
			if ($objectFolder &&  !$this->validateFolderAccess($objectFolder)) {
				$invalidObjectIds[] = $object->id;
				$lockedOutFolderIds[] = $objectFolder->id;
			}
		}

		if(!empty($invalidObjectIds)) {
			PardotLogger::getInstance()->info(
				json_encode([
					'W-5494870' => 'permission validation failed',
					'account_id' => $this->accountId,
					'user_id' => $this->apiActions->apiUser->id,
					'api_module' => $this->apiActions->module,
					'api_action' => $this->apiActions->action,
					'asset_ids' => $invalidObjectIds,
					'folder_ids' => $lockedOutFolderIds
				])
			);
		}

		return $invalidObjectIds;
	}


}

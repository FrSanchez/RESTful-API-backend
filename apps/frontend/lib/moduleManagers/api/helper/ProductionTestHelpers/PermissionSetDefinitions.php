<?php

class PermissionSetDefinitions
{
	// The two files below can be found at licensing-management/config/licensing/metadata/B2bmaIntegration-1.uld.xml
	// Remember MAIN vs GA
	protected $main = "B2bmaIntegration-1.uld_232.xml";
	protected $ga = "B2bmaIntegration-1.uld_230.xml";
	protected $ga_minus_2 = "B2bmaIntegration-1.uld_228.xml";

	protected $entityPrefix = 'entityPermission';

	/**
	 * Returns an Array of every sObject defined in the XML based off a supported supplied API Version
	 * @param $apiVersion
	 * @return null
	 */
	public function getIntegrationUserProfile($apiVersion) {
		switch ($apiVersion) {
			case "50.0":
				$xml=simplexml_load_file(__DIR__ . DIRECTORY_SEPARATOR . $this->ga_minus_2);
				return $this->extractObjectCrudFromXml($xml);
			case "51.0":
				$xml=simplexml_load_file(__DIR__ . DIRECTORY_SEPARATOR . $this->ga);
				return $this->extractObjectCrudFromXml($xml);
			case "52.0":
				$xml=simplexml_load_file(__DIR__ . DIRECTORY_SEPARATOR . $this->main);
				return $this->extractObjectCrudFromXml($xml);
			default:
				PardotLogger::getInstance()->error("Integration User permissions checker queried for unsupported API version: " . $apiVersion);
				return null;
		}
	}

	/**
	 * @param $xmlFile
	 * @return array
	 */
	protected function extractObjectCrudFromXml($xmlFile)
	{
		if ($xmlFile == null) {
			return null;
		}
		// Create parent array
		$profileArray = array();

		foreach ($xmlFile->settingItems as $item) {

			// Iterate through XML to find only SObjects as defined by the 'entityPermission' prefix in the durableId
			if (strpos($item->durableId, $this->entityPrefix) !== false) {

				// Split the durableId into 3 arrays. Expects a durableId like 'entityPermission.Lead.Create'
				$array = explode('.', $item->durableId, 3);

				$sObject = $array[1];
				$permission = "Permissions" . $array[2];
				$permissionValue = filter_var($item->value, FILTER_VALIDATE_BOOLEAN);

				// Push the new permission to the SObject array
				$profileArray[$sObject][$permission] = $permissionValue;
			}
		}
		return $profileArray;
	}
}

<?php

namespace Api\Config\Objects\Import;

use Abilities;
use AbilitiesAccessRule;
use AbilitiesManager;
use AccountSettingsConstants;
use AccountSettingsManager;
use Api\Exceptions\ApiException;
use Api\Framework\ApiRequestFiles;
use Api\Framework\FileInput;
use Api\Gen\Representations\ImportCreateColumnRepresentation;
use Api\Gen\Representations\ImportRepresentation;
use Api\Objects\Access\AccessException;
use Api\Objects\ObjectDefinitionCatalog;
use apiActions;
use ApiErrorLibrary;
use ApiFrameworkConstants;
use ApiManager;
use BackgroundQueuePeer;
use BaseApiRequestManager;
use Doctrine_Transaction_Exception;
use Exception;
use FineUploaderS3;
use GraphiteClient;
use ImportCampaignLocationConstants;
use ImportColumnHelper;
use ImportColumnParameterConstants;
use ImportManager;
use ImportParameterConstants;
use ImportS3Client;
use importTools;
use ImportWizardS3;
use LogConstants;
use LogMetrics;
use LogTimer;
use Pardot\Aws\AwsManager;
use Pardot\Constants\ShardDb\Import\Base\BaseStatusConstants;
use Pardot\Constants\ShardDb\Import\OriginConstants as ImportOriginConstants;
use Pardot\Constants\ShardDb\Import\StatusConstants as ImportStatusConstants;
use PardotLogger;
use piBackgroundQueue;
use piBackgroundQueueTable;
use piImport;
use piImportFile;
use piImportFileTable;
use piImportTable;
use piUser;
use ProspectDeduplicationManager;
use ProspectFieldDefaultPeer;
use RESTClient;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use ShardManager;
use stringTools;
use piUserTable;
use arrayTools;

class ImportSaveManager
{
	private ?LogMetrics $importLogger = null;
	private ?bool $multiplicityEnabled = null;
	private piImportTable $piImportTable;
	private piBackgroundQueueTable $piBackgroundQueueTable;
	private ImportS3Client $importS3Client;
	private $importId;
	protected ?ApiManager $apiManager = null;
	protected ?ImportColumnHelper $importColumnHelper = null;
	protected ?int $emailDefaultId = null;
	protected ?piImportFileTable $piImportFileTable;
	protected int $max_file_upload_size = 10 * 1024 * 1024; // 10MB
	public const MAX_BATCHES_PER_IMPORT = 10;

	public const INVALID_IMPORT_FIELDS_WITH_DECOUPLE_DNE = [
		'id',
		'unsubscribe',
		'subscribe',
		'pardot_hard_bounced',
		'email_bounced_reason',
		'email_bounced_date',
		'last_scored_at',
	];
	public const INVALID_IMPORT_FIELDS = [
		'id',
		'unsubscribe',
		'subscribe',
		'opted_out',
		'pardot_hard_bounced',
		'email_bounced_reason',
		'email_bounced_date',
		'last_scored_at',
	];
	public const DEFAULT_NULL_OVERWRITE = false;
	public const DEFAULT_OVERWRITE = true;
	private ShardManager $shardManager;

	public const IMPORT_SPECIAL_COLUMNS = [
		'matchid',
		'matchemail',
		'matchsalesforceid',
		'addtolist',
		'removefromlist'
	];

	public function __construct($piImportTable = null, $piBackgroundQueueTable = null, $importS3Client = null, $importColumnHelper = null, $shardManager = null)
	{
		$this->piImportTable = $piImportTable ?? piImportTable::getInstance();
		$this->piBackgroundQueueTable = $piBackgroundQueueTable ?? piBackgroundQueueTable::getInstance();
		$this->importS3Client = $importS3Client ?? ImportS3Client::getInstance();
		$this->importColumnHelper = $importColumnHelper;
		$this->shardManager = $shardManager ?? ShardManager::getInstance();
	}

	/**
	 * Transform the user provided options into a map keyed by the field name, with the values that will be used by the wizard
	 * for overwrite and nullOverwrite set to 0|1 depending on the requested value true|false|null
	 * a null value means the default will take effect
	 * @param ImportFieldRepresentation[] $csvColumnOptions
	 * @return array
	 */
	public function sanitizeFieldOptions(array $csvColumnOptions, int $accountId, int $apiVersion): array
	{
		// Note: This function is not called for v3/4. In those versions we have column options, not field options.
		$columnOptions = [];
		foreach ($csvColumnOptions as $field => $option) {
			if (!empty($field)) {
				$columnOptions[$field] = [];
				if ($option->getIsOverwriteSet()) {
					$columnOptions[$field]['overwrite'] = $option->getOverwrite();
				}
				if ($option->getIsNullOverwriteSet()) {
					$columnOptions[$field]['nullOverwrite'] = $option->getNullOverwrite();
				}
			} else {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_MISSING_PROPERTY,
					"Each column option must include a field name",
					RESTClient::HTTP_BAD_REQUEST
				);
			}
		}

		return $this->convertFieldOptionsToPreV5($columnOptions, $accountId, $apiVersion);
	}

	private function reportStats(int $accountId, array $columns)
	{
		$fieldCount = count(array_filter(array_column($columns, 'default_id')));
		$countCustom = count(array_filter(array_column($columns, 'custom_id')));

		GraphiteClient::gauge("api.prospect.import.upsert.customFields.{$accountId}", $countCustom, .2);
		GraphiteClient::gauge("api.prospect.import.upsert.prospectFields.{$accountId}", $fieldCount, .2);
	}

	/**
	 * @param FileInput|null $fileInput
	 * @param array|null $columnOptions
	 * @param int $accountId
	 * @param apiActions $apiActions
	 * @param bool $createOnNoMatch
	 * @return array|int[]|string[]
	 * @throws Exception
	 */
	public function verifyInputFile(?FileInput $fileInput, ?array $columnOptions, int $accountId, apiActions $apiActions, bool $createOnNoMatch = false)
	{
		$apiVersion = $apiActions->version;
		if (is_null($fileInput)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_FILE,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		// verify that the source file is a CSV
		if ($fileInput->getSize() > $this->getMaxFileUploadSize()) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_OVER_LIMIT,
				'Limit of 10MB exceeded',
				RESTClient::HTTP_REQUEST_TOO_LARGE
			);
		}

		$loadTime = new LogTimer(LogConstants::IMPORT_LOAD_CSV_FILE);

		try {
			$sourceResource = fopen($fileInput->toFileInputContent()->getPath(), "r");
			$csvFirstTwoLines = importTools::readCsvFirstTwoLines($sourceResource);
		} catch (Exception $e) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_CSV_FILE,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		} finally {
			$this->getImportLogger()->addTimer($loadTime);
		}

		if ($csvFirstTwoLines === false || empty($csvFirstTwoLines) || count($csvFirstTwoLines) === 1) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_CSV_FILE,
				'The file is empty or contains no data',
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$verifyTimer = new LogTimer(LogConstants::IMPORT_VERIFY_CSV_FILE);
		// grab the first line from the CSV and use as the header
		$csvHeaders = array_map('trim', $csvFirstTwoLines[0]);

		// verify that the fields in the header are unique
		$duplicateHeaders = $this->getFirstDuplicateValue($csvHeaders);

		if (!is_null($duplicateHeaders)) {
			$duplicateHeaderValue = $csvHeaders[$duplicateHeaders[0]];
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_CSV_FILE,
				"CSV header contains the same field multiple times: {$duplicateHeaderValue} (at index {$duplicateHeaders[0]} and {$duplicateHeaders[1]})",
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		//convert to preV5 name
		if ($apiVersion >= 5) {
			$csvHeaders = $this->convertCsvHeadersToPreV5($csvHeaders, $accountId, $apiVersion);
		}

		$this->validateColumnOptions($csvHeaders, $columnOptions, $apiVersion);

		//store V5 names in a map with key-value (V3-name: V5-name) & convert csvHeaders into array
		if ($apiVersion >= 5) {
			$csvHeaderNamesV5 = $csvHeaders;
			$csvHeaders = array_keys($csvHeaders);
		}

		// check if any disallowed fields are included in headers
		if (AccountSettingsManager::getInstance($accountId)->isFlagEnabled(AccountSettingsConstants::FEATURE_DECOUPLE_DNE)) {
			$invalidFields = array_intersect(self::INVALID_IMPORT_FIELDS_WITH_DECOUPLE_DNE, $csvHeaders);
		} else {
			$invalidFields = array_intersect(self::INVALID_IMPORT_FIELDS, $csvHeaders);
		}
		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_BULK_API_FIELDS_INVALID,
				implode(',', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$this->getImportLogger()->addTimer($verifyTimer);
		$allowAddToListColumn = $apiVersion >= 5 ;
		// verify that the header line contains a list of fields and create the columns
		// default to overwriting values in the DB - TODO: pass in column overwrite prefs if provided
		list($columns, $invalidFields, $invalidUserColumns, $requiredFieldErrors) = $this->getImportColumnHelper($accountId)->createImportColumns(
			$accountId,
			$csvHeaders,
			self::DEFAULT_OVERWRITE,
			self::DEFAULT_NULL_OVERWRITE,
			$columnOptions ?? [],
			$allowAddToListColumn,
			$allowAddToListColumn,
			$apiVersion >= 5,
			$apiVersion >= 5
		);

		if (!empty($invalidFields)) {
			if ($apiVersion >= 5) {
				for ($i = 0; $i < count($invalidFields); ++$i) {
					$invalidFields[$i] = $csvHeaderNamesV5[$invalidFields[$i]];
				}
			}
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_BULK_API_FIELDS_INVALID,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		if (!empty($invalidUserColumns)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_BULK_API_FIELDS_INVALID,
				implode(', ', $invalidUserColumns),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		if (!empty($requiredFieldErrors)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_IMPORT_MISSING_REQUIRED_FIELD,
				implode(', ', $requiredFieldErrors),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		//check if matchX is set in columns
		if ($apiVersion >= 5) {
			$matchColumns = [];
			foreach ($columns as $column) {
				if (array_key_exists(ImportColumnParameterConstants::MATCH_ID, $column)) {
					$matchColumns[] = "matchId";
				}
				if (array_key_exists(ImportColumnParameterConstants::MATCH_EMAIL, $column)) {
					$matchColumns[] = "matchEmail";
				}
				if (array_key_exists(ImportColumnParameterConstants::MATCH_SALESFORCEID, $column)) {
					$matchColumns[] = "matchSalesforceId";
				}
			}
			$matchColumnCount = count($matchColumns);
			if ($matchColumnCount > 1) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_INVALID_CSV_FILE,
					"Only one of 'matchId', 'matchEmail', and 'matchSalesforceId' may appear in import column headers.",
					RESTClient::HTTP_BAD_REQUEST
				);
			}
		}

		$missingFields = [];
		if ($apiVersion >= 5) {
			if ($createOnNoMatch || !$matchColumnCount) {
				// ensure email and campaignId fields are defined
				if (!in_array('email', $csvHeaders)) {
					$missingFields[] = "'email' column";
				}
				if (!in_array(ImportColumnParameterConstants::CAMPAIGN_ID, $csvHeaders)) {
					$missingFields[] = "'campaignId' column";
				}
			}
		} else {
			// ensure an email field is defined
			$emailProspectFieldDefaultId = $this->getEmailDefaultId($accountId);
			$defaultIds = array_column($columns, 'default_id');
			if (!in_array($emailProspectFieldDefaultId, $defaultIds)) {
				$missingFields[] = 'email';
			}
		}
		if ($missingFields) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_MISSING_PARAMETERS,
				implode(', ', $missingFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		$containsImportSpecial = !empty(array_intersect(
			self::IMPORT_SPECIAL_COLUMNS,
			array_map('strtolower', $csvHeaders)
		));
		//if it is v5 and remove or add ToList column is present, check if they have permission to view list
		if ($apiVersion >= 5 && $containsImportSpecial
			&& !$this->hasListViewPermission($apiActions)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_LIST_ID,
				null,
				RESTClient::HTTP_NOT_FOUND
			);
		}

		//set csvHeadersV5, to generate error file with correct V5 field names
		if ($apiVersion >= 5) {
			$csvHeadersV5 = [];
			foreach ($csvHeaderNamesV5 as $preV5Name => $V5Name) {
				$csvHeadersV5[] = $V5Name;
			}
			$fileInput->setVar('csvHeadersV5', $csvHeadersV5);
		}
		$fileInput->setVar('csvHeaders', $csvHeaders);
		$fileInput->setVar('columns', $columns);
		$this->reportStats($accountId, $columns);
		return ($apiVersion >= 5) ? $csvHeadersV5 : $csvHeaders;
	}

	/**
	 * Checks if the apiUser in $apiActions has the MARKETING_SEGMENTATION_LISTS_VIEW
	 * @param apiActions $apiActions
	 * @return bool if the apiUser has the MARKETING_SEGMENTATION_LISTS_VIEW
	 */
	protected function hasListViewPermission($apiActions): bool
	{
		return AbilitiesManager::getInstance()->evaluateAccessRule(
			AbilitiesAccessRule::allOf(Abilities::MARKETING_SEGMENTATION_LISTS_VIEW),
			BaseApiRequestManager::getUserAbilitiesFromRequest($apiActions, AbilitiesManager::getInstance())
		);
	}

	/**
	 * Given an array of strings, returns the first duplicate within the array.
	 * @param string[] $values an array of strings
	 * @return array|null If the array contains a duplicate, then the indexes of the first two duplicates is returned.
	 * Otherwise null is returned.
	 */
	private function getFirstDuplicateValue(array $values)
	{
		if (!$values || count($values) == 0) {
			return null;
		}

		$lowerToOriginalPositions = [];
		for ($i = 0; $i < count($values); $i++) {
			$value = $values[$i];
			$lowerValue = strtolower(trim($value));
			if (array_key_exists($lowerValue, $lowerToOriginalPositions)) {
				return [$lowerToOriginalPositions[$lowerValue], $i];
			}
			$lowerToOriginalPositions[$lowerValue] = $i;
		}
		return null;
	}

	public function getImportLogger(): LogMetrics
	{
		if (!$this->importLogger) {
			$this->importLogger = new LogMetrics(LogConstants::API_IMPORT_PREFIX);
		}

		return $this->importLogger;
	}

	/**
	 * @param $csvHeaders
	 * @param $columnOptions
	 * @param $apiVersion
	 * @return void
	 */
	public function validateColumnOptions($csvHeaders, $columnOptions, $apiVersion)
	{
		if ($columnOptions == null || count($columnOptions) == 0) {
			// when columnOptions was not specified, we just ignore it
			return;
		}

		if ($apiVersion >= 5) {
			$csvHeadersLower = array_keys($csvHeaders);
		} else {
			$csvHeadersLower = array_map('strtolower', $csvHeaders);
		}

		$userColumns = array_map('strtolower', array_keys($columnOptions));
		$leftDiff = [];
		if ($apiVersion < 5) {
			$leftDiff = array_diff($csvHeadersLower, $userColumns);
		}
		$rightDiff = array_diff($userColumns, $csvHeadersLower);

		if (($rightDiff && count($rightDiff) > 0)) {
			if ($apiVersion >= 5) {
				$rightDiffNames = [];
				foreach ($rightDiff as $field) {
					$rightDiffNames[] = $columnOptions[$field]['name'] ?? $field;
				}
				$rightDiff = $rightDiffNames;
			}
		}

		$diff = array_merge($leftDiff, $rightDiff);
		if ($diff) {
			throw new ApiException(
				($apiVersion >= 5) ? ApiErrorLibrary::API_ERROR_IMPORT_CSV_HEADER_AND_FIELD_OPTIONS_MISMATCH :
					ApiErrorLibrary::API_ERROR_IMPORT_CSV_HEADER_AND_COLUMN_OPTIONS_MISMATCH,
				implode(', ', $diff),
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	/**
	 * @return boolean
	 */
	public function isMultiplicityEnabled($accountId)
	{
		if (null === $this->multiplicityEnabled) {
			$this->multiplicityEnabled = ProspectDeduplicationManager::getInstance($accountId)
				->isMultiplicityEnabledAndWritable();
		}
		return $this->multiplicityEnabled;
	}

	/**
	 * @param piUser $user
	 * @param ?int $state
	 * @param ?array $columnOptions
	 * @param bool $restoreDeleted
	 * @param bool $createOnNoMatch
	 * @param ?FileInput $file
	 * @param bool $internalRequest
	 * @param int $apiVersion
	 * @return piImport
	 * @throws Exception
	 */
	public function doCreate($user, $state, $columnOptions, $restoreDeleted, $createOnNoMatch, $file, $internalRequest, $apiVersion): piImport
	{
		$import = $this->createNewImport($state, $user->id, $user->account_id, $internalRequest);

		// create the background queue
		$backgroundQueue = $this->createEmptyInitializeBackgroundQueue($user->account_id, $user->id);
		$createParameters = [
			ImportParameterConstants::COLUMN_OPTIONS => $columnOptions,
			ImportParameterConstants::RESTORE_ARCHIVED => $restoreDeleted,
			ImportParameterConstants::CREATE_ON_NO_MATCH => $createOnNoMatch,
			ImportParameterConstants::IMPORT_ID => $import->id,
			ImportParameterConstants::CAMPAIGN_LOCATION => ImportCampaignLocationConstants::IN_ROW,
			ImportParameterConstants::FROM => ImportParameterConstants::FROM_API,
			ImportParameterConstants::VERSION => $apiVersion,
		];
		$backgroundQueue->parameters = serialize($createParameters);

		// if the user provided a file, add the file
		if (!is_null($file)) {
			$this->addFileToImport($import, $backgroundQueue, $file, $user->id, $user->account_id, $this->isMultiplicityEnabled($user->account_id));
		}

		$backgroundQueue->is_ready = $state === 1;
		$backgroundQueue->save();

		$import->background_queue_id = $backgroundQueue->id;
		$import->save();
		$this->getImportLogger()->record('saveNewRecord');
		return $import;
	}


	/**
	 * Creates a new import and populates with default data
	 * @param int|null $status
	 * @param $userId
	 * @param $accountId
	 * @param $isInternalRequest
	 * @return piImport
	 * @throws Exception
	 */
	private function createNewImport(?int $status, $userId, $accountId, $isInternalRequest)
	{
		/** @var $import piImport */
		$import = $this->piImportTable->create();
		$import->user_id = $userId;
		$import->account_id = $accountId;
		if ($isInternalRequest) {
			$import->origin = ImportOriginConstants::API_INTERNAL;
		} else {
			$import->origin = ImportOriginConstants::API_EXTERNAL;
		}
		if (is_null($status)) {
			$import->status = ImportStatusConstants::OPEN;
		} else {
			$import->status = $this->convertStateInputToImportStatus($status);
		}
		$import->save();
		return $import;
	}

	/**
	 * @param int|null $value
	 * @return int
	 */
	private function convertStateInputToImportStatus(?int $value): int
	{
		if ($value === ImportStatusConstants::OPEN) {
			return ImportStatusConstants::OPEN;
		} elseif ($value === ImportStatusConstants::WAITING || $value == ImportStatusConstants::READY) {
			return ImportStatusConstants::WAITING;
		} else {
			throw new RuntimeException("Unable to convert input state to ImportStatus: $value");
		}
	}

	/**
	 * @param $import piImport
	 * @param $backgroundQueue piBackgroundQueue
	 * @param FileInput $importFile ?FileInput
	 * @param $userId
	 * @param $accountId
	 * @param $isMultiplicityEnabled
	 * @throws Exception
	 */
	public function addFileToImport(piImport &$import, piBackgroundQueue &$backgroundQueue, FileInput $importFile, $userId, $accountId, $isMultiplicityEnabled)
	{
		$s3File = $this->uploadToS3($importFile->toFileInputContent()->getPath(), $accountId, $userId);
		$s3Key = $s3File['s3Key'];
		$s3Bucket = $s3File['s3Bucket'];
		$sourceFilename = $importFile->getName();
		$fileEntry = [
			's3_bucket' => $s3Bucket,
			's3_key' => $s3Key,
			'filename' => $sourceFilename,
		];

		$parameters = unserialize($backgroundQueue->parameters);

		// If this is the first file, save metadata about the file within the initial background_queue
		$fileCount = $this->getImportFileTable()->countApiImportFiles($accountId, $import->id);
		if ($fileCount == 0) {
			$import->s3_bucket = $s3Bucket;
			$import->s3_key = $s3Key;
			$import->filename = $importFile->getName();
			$import->storage_location = AwsManager::STORAGE_LOCATION_AWS;

			$this->apiVersion = arrayTools::safeGet($parameters, 'version', $isMultiplicityEnabled ? 4 : 3);

			$importWizard = $this->createImportWizard($s3File, $importFile, $userId, $isMultiplicityEnabled, $this->apiVersion);

			// get the wizard parameters and allow the input parameters to override the parameters
			$importWizardParameters = $importWizard->getImportParameters();
			$parameters = array_merge($importWizardParameters, $parameters);

			$parameters[ImportParameterConstants::IMPORT_WIZARD] = $importWizard;
			$parameters[ImportParameterConstants::CAMPAIGN_LOCATION] = ImportCampaignLocationConstants::IN_ROW;
			unset($parameters[ImportParameterConstants::IMPORT_CAMPAIGN_ID]);

			// create a new array of file entries
			$parameters[ImportParameterConstants::FILES] = [$fileEntry];
		} else {
			// add the file to the existing array of entries
			$parameters[ImportParameterConstants::FILES][] = $fileEntry;
		}

		$backgroundQueue->parameters = serialize($parameters);

		/** @var piImportFile $importFileRow */
		$importFileRow = $this->getImportFileTable()->create();
		$importFileRow->import_id = $import->id;
		$importFileRow->account_id = $accountId;
		$importFileRow->s3_bucket = $s3File['s3Bucket'];
		$importFileRow->s3_key = $s3File['s3Key'];
		$importFileRow->filename = $sourceFilename;
		$importFileRow->created_by = $userId;
		$importFileRow->save();
	}

	/**
	 * @param array $s3File
	 * @param FileInput $importFile
	 * @param int $userId
	 * @param bool $isMultiplicityEnabled
	 * @param int $apiVersion
	 * @return ImportWizardS3
	 */
	private function createImportWizard(array $s3File, FileInput $importFile, $userId, $isMultiplicityEnabled, $apiVersion): ImportWizardS3
	{
		$s3Key = $s3File['s3Key'];
		$s3Uuid = $s3File['s3Uuid'];
		$s3Bucket = $s3File['s3Bucket'];
		$sourceFilename = $importFile->getName();
		$firstRow = $importFile->getVar('csvHeaders');
		$columns = $importFile->getVar('columns');

		//pass V5 headers if they exist
		if ($apiVersion >= 5) {
			$firstRowV5 = $importFile->getVar('csvHeadersV5');
		}

		// create the ImportWizard. This really shouldn't occur in the API but it's currently required by the Import Jobs;
		// this code should be refactored to remove the dependency.
		$importWizard = new ImportWizardS3();
		$importWizard->setS3Bucket($s3Bucket);
		$importWizard->setS3Key($s3Key);
		$importWizard->setS3UUID($s3Uuid);
		$importWizard->setFilename($sourceFilename);
		$importWizard->setImportParameter(ImportParameterConstants::CREATED_USER_ID, $userId);
		$importWizard->setImportParameter('import_mode', $this->getImportMode($firstRow, $isMultiplicityEnabled, $apiVersion));
		$importWizard->setHeaderRow(importTools::arrayToCsv([$firstRow]));
		$importWizard->setImportParameter(ImportParameterConstants::HEADER_ROW, importTools::arrayToCsv([$firstRow]));
		$importWizard->setFirstTwoCSVRows(importTools::arrayToCsv([$firstRow]));
		$importWizard->setImportParameter(ImportParameterConstants::COLUMNS, $columns);

		//add V5 headers to the importWizard if they exist
		if ($apiVersion >= 5) {
			$importWizard->setImportParameter(ImportParameterConstants::HEADER_ROW_V5, importTools::arrayToCsv([$firstRowV5]));
		}
		return $importWizard;
	}

	/**
	 * @return piImportFileTable
	 */
	private function getImportFileTable()
	{
		if (!isset($this->piImportFileTable)) {
			$this->piImportFileTable = piImportFileTable::getInstance();
		}
		return $this->piImportFileTable;
	}


	/**
	 * Uploads the given file to S3 to a bucket and key, which are returned when successfully uploaded.
	 *
	 * @param $sourceFile string the path of the file to upload.
	 * @return array
	 * @throws Exception
	 */
	private function uploadToS3(string $sourceFile, int $accountId, $userId)
	{
		// create the S3 location info
		$s3Folder = str_pad($accountId, 10, "0", STR_PAD_LEFT);
		$s3Bucket = FineUploaderS3::GetBucket();
		$s3Uuid = Uuid::uuid4()->toString();
		$s3Key = $s3Folder . "/" . $s3Uuid . ".csv";
		$beforeUpload = microtime(true);
		$s3Client = $this->importS3Client->getS3Client();
		$s3Client->putObject([
			'Bucket' => $s3Bucket,
			'Key' => $s3Key,
			'SourceFile' => $sourceFile
		]);
		$s3Client->waitUntil('ObjectExists', [
			'Bucket' => $s3Bucket,
			'Key' => $s3Key
		]);
		$this->logVerbose("Uploaded CSV file to S3", [
			"file" => $sourceFile,
			"s3Bucket" => $s3Bucket,
			"s3Key" => $s3Key,
			"duration" => (microtime(true) - $beforeUpload) * 1000
		], $accountId, $userId);
		return [
			's3Bucket' => $s3Bucket,
			's3Key' => $s3Key,
			's3Uuid' => $s3Uuid
		];
	}


	/**
	 * Adds a log message only when the "api_import.verbose_logging" feature flag is enabled.
	 * @param string $message the message
	 * @param array $context the context to be written along with the log message
	 */
	private function logVerbose(string $message, array $context, int $accountId, $userId)
	{
		if (AccountSettingsManager::accountHasFeatureEnabled($accountId, AccountSettingsConstants::FEATURE_API_IMPORT_VERBOSE_LOGGING)) {
			$this->infoLog($message, $context, $accountId, $userId);
		}
	}

	/**
	 * Adds a log message with some extra context.
	 * @param string $message the message
	 * @param array $context the context to be written along with the log message
	 */
	private function infoLog(string $message, array $context, $accountId, $userId)
	{
		// always set these values in the context
		$context['importId'] = $this->importId;
		$context['userId'] = $userId;
		$context['accountId'] = $accountId;

		PardotLogger::getInstance()->info($message, $context);
	}


	/**
	 * Creates the basic background queue with empty data
	 * @return piBackgroundQueue
	 * @throws Exception
	 */
	protected function createEmptyInitializeBackgroundQueue($accountId, $userId)
	{
		/** @var piBackgroundQueue $bgQueue */
		$bgQueue = $this->piBackgroundQueueTable->create();
		$bgQueue->account_id = $accountId;
		$bgQueue->user_id = $userId;
		$bgQueue->created_by = $userId;
		$bgQueue->is_ready = false;
		$bgQueue->type = BackgroundQueuePeer::TYPE_IMPORT_INITIALIZE_SCALING;
		return $bgQueue;
	}

	/**
	 * @param string[] $headerRow
	 * @param bool $isMultiplicityEnabled
	 * @param int $apiVersion
	 * @return int
	 */
	protected function getImportMode(array $headerRow, bool $isMultiplicityEnabled, $apiVersion)
	{
		$headerRowLowercase = array_map("strtolower", $headerRow);

		if ($apiVersion >= 5) {
			foreach ($headerRow as $columnName) {
				if (trim(strtolower($columnName)) == ImportColumnParameterConstants::MATCH_ID || $columnName == ImportColumnParameterConstants::MATCH_EMAIL || $columnName == ImportColumnParameterConstants::MATCH_SALESFORCEID) {
					return ImportManager::MODE_MATCH_EXPLICIT;
				}
			}
			return ImportManager::MODE_NO_MATCHING;
		} else {
			if ($isMultiplicityEnabled &&
				in_array(strtolower(ImportColumnParameterConstants::SALESFORCE_FID), $headerRowLowercase)) {
				return ImportManager::MODE_MATCH_BY_SALESFORCE_ID;
			}

			if (in_array(strtolower(ImportColumnParameterConstants::PROSPECT_ID), $headerRowLowercase)) {
				return ImportManager::MODE_MATCH_BY_ID_OR_EMAIL;
			}

			return ImportManager::MODE_MATCH_BY_EMAIL;
		}
	}

	/**
	 * @return int
	 */
	protected function getEmailDefaultId($accountId)
	{
		if (!is_null($this->emailDefaultId)) {
			return $this->emailDefaultId; // Used for tests only.
		}
		return ProspectFieldDefaultPeer::retrieveIdByFieldIdCached(ProspectFieldDefaultPeer::FIELD_EMAIL, $accountId);
	}

	/**
	 * @param piUser $user
	 * @param bool $isInternalRequest
	 */
	public function validateDailyImportBatchCount($user, $isInternalRequest)
	{
		$maxImportBatchesPerDay = $user->piAccount->getAccountLimit()->getMaxImportBatchesPerDay();
		if ($this->getDailyImportBatchCount($user->account_id, $isInternalRequest) >= $maxImportBatchesPerDay) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_IMPORT_BATCH_DAILY_LIMIT,
				$maxImportBatchesPerDay,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	public function validateCreateState($state): int
	{
		if ($state == BaseStatusConstants::OPEN) {
			return $state;
		}
		/** {@see ImportStatusTypeEnum} */
		if ($state == ImportStatusConstants::READY) {
			return BaseStatusConstants::WAITING;
		}
		throw new ApiException(
			ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
			"Requested state must be " . stringTools::generateHumanReadableList(['open', 'ready']),
			RESTClient::HTTP_BAD_REQUEST
		);
	}

	/**
	 * Gets the number of batches that have already been created within the "day". See the calculation in
	 * ApiManager::getDailyImportBatchCount for how "daily" is figured out.
	 * @return int the number of batches created today
	 */
	private function getDailyImportBatchCount($accountId, bool $internalRequest): int
	{
		if ($internalRequest) {
			$origin = ImportOriginConstants::API_INTERNAL;
		} else {
			$origin = ImportOriginConstants::API_EXTERNAL;
		}

		return $this->getApiManager($accountId)->getDailyImportBatchCount($origin);
	}

	/**
	 * @param int $accountId
	 * @return ApiManager
	 */
	protected function getApiManager(int $accountId)
	{
		if (!isset($this->apiManager)) {
			$this->apiManager = new ApiManager($accountId);
		}

		return $this->apiManager;
	}

	private function getImportColumnHelper(int $accountId)
	{
		if (!$this->importColumnHelper) {
			$this->importColumnHelper = new ImportColumnHelper($accountId);
		}
		return $this->importColumnHelper;
	}

	/**
	 * @param piImport $import
	 * @param piUser $user
	 * @throws Doctrine_Transaction_Exception
	 * @throws \Doctrine_Validator_Exception
	 */
	public function doUpdate(piImport $import, piUser $user)
	{
		$importDoUpdateStat = 'api.request.import.update.' . strtolower($this->getRoleName($user));
		GraphiteClient::increment($importDoUpdateStat, 0.05);

		$backgroundQueueId = $import->background_queue_id;
		if (is_null($backgroundQueueId)) {
			PardotLogger::getInstance()->error('Cannot execute import update. No background queue found');
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_IMPORT_MISSING_BGQUEUE,
				null,
				RESTClient::HTTP_INTERNAL_SERVER_ERROR
			);
		}

		$shardConnection = $this->shardManager->getDoctrineShardConnection();
		$shardConnection->beginTransaction();
		try {
			$import->status = ImportStatusConstants::WAITING;
			$import->save();

			// make the background_queue ready
			$backgroundQueue = $this->piBackgroundQueueTable->findOneByIdAndAccountId($backgroundQueueId, $user->account_id);
			if (is_null($backgroundQueue)) {
				PardotLogger::getInstance()->error("Cannot execute import update. Could not find background queue with id:{$backgroundQueueId}");
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_IMPORT_MISSING_BGQUEUE,
					null,
					RESTClient::HTTP_INTERNAL_SERVER_ERROR
				);
			}
			$backgroundQueue->is_ready = true;
			$backgroundQueue->save();

			$shardConnection->commit();
		} catch (Exception $e) {
			$shardConnection->rollback();
			throw $e;
		}
	}

	/**
	 * @param piImport $piImport
	 * @param int $accountId
	 */
	public function validateUpdate(piImport $import, int $accountId, string $state)
	{
		/**
		 * v3/v4 calls will come as WAITING, V5 is sent as READY
		 */
		if ($state != ImportStatusConstants::WAITING && $state != ImportStatusConstants::READY) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PROPERTY,
				"Requested state must be \"Ready\"",
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		// make sure the import is "Open"
		if (is_null($import->status) || $import->status != ImportStatusConstants::OPEN) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_IMPORT_NOT_OPEN, null, RESTClient::HTTP_BAD_REQUEST);
		}

		if ($import->is_expired) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_ACTION, 'Cannot update an expired import', RESTClient::HTTP_BAD_REQUEST);
		}

		// make sure that the import has files associated
		$fileCount = $this->getImportFileTable()->countApiImportFiles($accountId, $import->id);
		if ($fileCount == 0) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_IMPORT_NO_DATA,
				null,
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	public function getMaxFileUploadSize()
	{
		return $this->max_file_upload_size;
	}

	/**
	 * @param apiActions $apiActions
	 * @param int $importId
	 * @return piImport
	 */
	public function loadImport(apiActions $apiActions, ?int $importId): piImport
	{
		$userId = $apiActions->apiUser->id;
		$accountId = $apiActions->apiUser->account_id;
		$internalRequest = $apiActions->isInternalRequest();
		// make sure the import with the ID exists
		$import = $this->piImportTable->findOneByIdAndAccountId($importId, $accountId);

		if (!$import) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND, null, RESTClient::HTTP_NOT_FOUND);
		}

		// make sure the same user is reading the import data or user has admin import view permissions
		if ($userId != $import->user_id
			&& !AbilitiesManager::getInstance()->evaluateAccessRule(
				AbilitiesAccessRule::allOf(Abilities::ADMIN_IMPORTS_VIEW),
				BaseApiRequestManager::getUserAbilitiesFromRequest($apiActions, AbilitiesManager::getInstance())
			)
		) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND, null, RESTClient::HTTP_NOT_FOUND);
		}

		// don't allow imports that were generated from outside the API to appear in the API
		if (!ImportOriginConstants::isApi($import->origin)) {
			PardotLogger::getInstance()->warn('Attempt to access non API (' . ImportOriginConstants::getNameFromValue($import->origin) . ') import from API');
			throw new ApiException(ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND, null, RESTClient::HTTP_NOT_FOUND);
		}

		// don't allow internal consumers to retrieve imports from external and vice versa
		if ($internalRequest && $import->origin == ImportOriginConstants::API_EXTERNAL ||
			!$internalRequest && $import->origin == ImportOriginConstants::API_INTERNAL) {
			PardotLogger::getInstance()->warn('Attempt to access non API (' . ImportOriginConstants::getNameFromValue($import->origin) . ') import from API');
			throw new ApiException(ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND, null, RESTClient::HTTP_NOT_FOUND);
		}

		return $import;
	}

	public function validateBatch(apiActions $apiActions, ?piImport $import = null)
	{
		if (!$import) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND, null, RESTClient::HTTP_NOT_FOUND);
		}

		$accountId = $apiActions->apiUser->account_id;
		// verify that the account hasn't exceeded their daily import limit
		$this->validateDailyImportBatchCount($apiActions->apiUser, $apiActions->isInternalRequest());

		// make sure the import is "Open"
		if (is_null($import->status) || $import->status != ImportStatusConstants::OPEN) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_IMPORT_INVALID_STATE, 'Current status is ' . $this->getImportStatusForApi($import->status), RESTClient::HTTP_BAD_REQUEST);
		}

		if ($import->is_expired) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_ACTION, 'Cannot add a batch to an expired import', RESTClient::HTTP_BAD_REQUEST);
		}

		// make sure that the import doesn't already have the max number of files associated.
		$fileCount = $this->getImportFileTable()->countApiImportFiles($accountId, $import->id);
		if ($fileCount >= self::MAX_BATCHES_PER_IMPORT) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_IMPORT_BATCH_LIMIT, 'Over batch per import limits (Currently set to ' . self::MAX_BATCHES_PER_IMPORT . ')', RESTClient::HTTP_TOO_MANY_REQUESTS);
		}

		$parameters = unserialize($import->piBackgroundQueue->parameters);
		$createVersion = (int)$parameters[ImportParameterConstants::VERSION];
		$columnOptions = $parameters[ImportParameterConstants::COLUMN_OPTIONS];

		if ($createVersion != $apiActions->version) {
			throw new ApiException(ApiErrorLibrary::API_ERROR_INVALID_VERSION_MIX, null, RESTClient::HTTP_BAD_REQUEST);
		}

		// get the input file from the request
		$importFileParameter = $apiActions->version < 5 ? 'importFile' : 'file';
		$apiRequestFiles = new ApiRequestFiles(1);
		$importFile = $apiRequestFiles->getFileInputByName(
			$apiActions->getRequest(),
			$importFileParameter
		);
		// verify InputFile will send an error to the caller when the file is invalid or doesn't exist
		$this->verifyInputFile($importFile, $columnOptions, $accountId, $apiActions);

		// for any file after the first, the header_row must match the first file
		if ($fileCount > 0) {
			$backgroundQueueParameters = unserialize($import->piBackgroundQueue->parameters);
			if (is_null($backgroundQueueParameters) || !array_key_exists(ImportParameterConstants::HEADER_ROW, $backgroundQueueParameters)) {
				throw new RuntimeException('Import ' . $this->importId . ' referenced background_queue ' .
					$import->background_queue_id . ' which doesn\'t contain ' . ImportParameterConstants::HEADER_ROW);
			}

			$firstFileHeaderRow = importTools::csvToArray($backgroundQueueParameters[ImportParameterConstants::HEADER_ROW])[0];
			$thisHeaderRow = $importFile->getVar('csvHeaders');
			if (!importTools::compareHeaderRows($firstFileHeaderRow, $thisHeaderRow, $headerRowValidationError)) {
				throw new ApiException(
					ApiErrorLibrary::API_ERROR_BULK_API_FIELDS_INVALID,
					'Header row for file didn\'t match initial file',
					RESTClient::HTTP_BAD_REQUEST
				);
			}
		}

		return $importFile;
	}

	/**
	 * @param $import
	 * @param $importFile
	 * @param piUser $user
	 * @param $isMultiplicityEnabled
	 * @return void
	 * @throws Exception
	 */
	public function saveBatch(piImport $import, FileInput $importFile, piUser $user)
	{
		$importDoBatchStat = 'api.request.import.batch.' . strtolower($this->getRoleName($user));
		GraphiteClient::increment($importDoBatchStat, 0.05);
		$shardConnection = $this->shardManager->getDoctrineShardConnection();
		$shardConnection->beginTransaction();

		try {
			$backgroundQueue = $import->piBackgroundQueue;
			$this->addFileToImport($import, $backgroundQueue, $importFile, $user->id, $user->account_id, $this->isMultiplicityEnabled($user->account_id));
			$backgroundQueue->save();
			$import->save();
			$shardConnection->commit();
		} catch (Exception $e) {
			$shardConnection->rollback();
			throw $e;
		}
	}

	private function getRoleName($apiUser)
	{
		$apiUserRoleId = $apiUser->getRole();
		$apiUserRoleName = piUserTable::getDefaultRoleName($apiUserRoleId);
		if (is_null($apiUserRoleName)) {
			$apiUserRoleName = 'Custom';
		}
		return str_replace(" ", "", $apiUserRoleName);
	}

	/**
	 * Gets the value for the ImportStatusEnum within the API from the given DB value.
	 * @param int|null $dbValue
	 * @return string
	 */
	public function getImportStatusForApi(?int $dbValue): string
	{
		try {
			return ucfirst(strtolower(ImportStatusConstants::getNameFromValue($dbValue)));
		} catch (Exception $e) {
			PardotLogger::getInstance()->error("Import contains invalid status value");
			return "Unknown";
		}
	}

	/**
	 * @param piImport $import
	 * @param int $accountId
	 * @param ImportRepresentation $representation
	 * @return void
	 */
	public function validateUpdateWithRepresentation(piImport $import, int $accountId, ImportRepresentation $representation)
	{
		if ($representation->getIsOperationSet()
			|| $representation->getIsRestoreDeletedSet()
			|| $representation->getIsFieldsSet()
			|| $representation->getIsObjectSet()) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_PARAMETER,
				'Only status can be set',
				RESTClient::HTTP_BAD_REQUEST
			);
		}
		$this->validateUpdate($import, $accountId, $representation->getStatus());
	}

	public function validateDownloadErrors(piImport $import)
	{
		if ($import->status != ImportStatusConstants::COMPLETE) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_RECORD_NOT_FOUND,
				'Import is not finished processing',
				RESTClient::HTTP_BAD_REQUEST
			);
		}
	}

	public function doDownloadErrors(apiActions $apiActions, piImport $import, piUser $user)
	{
		$importDoDownloadStat = 'api.request.import.download.' .$this->getRoleName($user);
		GraphiteClient::increment($importDoDownloadStat, 0.05);
		$filePath = $import->getErrorFile($import->account_id);
		$apiActions->redirect($filePath);
	}

	/**
	 * @param array $csvHeaders
	 * @param int $accountId
	 * @param int $apiVersion
	 * @return array
	 * @throws Exception
	 */
	private function convertCsvHeadersToPreV5(array $csvHeaders, int $accountId, int $apiVersion): array
	{
		$objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($apiVersion, $accountId, "prospect");

		$preV5csvHeaders = [];
		$invalidFields = [];
		foreach ($csvHeaders as $field) {
			if (substr($field, -3) != ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX) {
				if (in_array(strtolower($field), self::IMPORT_SPECIAL_COLUMNS)) {
					$preV5csvHeaders[strtolower($field)] = $field;
				} else {
					if (strpos($field, '_')) {
						$invalidFields[] = $field;
					} else {
						$fieldDefinition = $objectDefinition->getStandardFieldByName($field);
						if ($fieldDefinition) {
							$preV5csvHeaders[$fieldDefinition->getPreV5Name()] = $field;
						} else {
							$invalidFields[] = $field;
						}
					}
				}
			} else {
				$preV5csvHeaders[strtolower($field)] = $field;
			}
		}

		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_CSV_FILE,
				"CSV header contains invalid default fields: " . implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $preV5csvHeaders;
	}

	/**
	 * @param array $columnOptions
	 * @param int $accountId
	 * @param int $apiVersion
	 * @return array
	 * @throws Exception
	 */
	private function convertFieldOptionsToPreV5(array $columnOptions, int $accountId, int $apiVersion): array
	{
		$objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType($apiVersion, $accountId, "prospect");

		$preV5columnOptions = [];
		$invalidFields = [];
		foreach ($columnOptions as $field => $options) {
			if (substr($field, -3) != ApiFrameworkConstants::CUSTOM_FIELD_API_SUFFIX) {
				if (strpos($field, '_')) {
					$invalidFields[] = $field;
				} else {
					$fieldDefinition = $objectDefinition->getStandardFieldByName($field);
					if ($fieldDefinition) {
						$options['name'] = $field;
						$preV5columnOptions[$fieldDefinition->getPreV5Name()] = $options;
					} else {
						$invalidFields[] = $field;
					}
				}
			} else {
				$preV5columnOptions[$field] = $options;
			}
		}

		if (!empty($invalidFields)) {
			throw new ApiException(
				ApiErrorLibrary::API_ERROR_INVALID_FIELDS,
				implode(', ', $invalidFields),
				RESTClient::HTTP_BAD_REQUEST
			);
		}

		return $preV5columnOptions;
	}
}

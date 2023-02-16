<?php

namespace Api\Config\Objects\Export\RecordActions;

use Api\Config\Objects\Export\ExportDoctrineQueryModifier;
use Api\Config\Objects\Export\ExportRepresentationHelper;
use Api\Config\Objects\Export\ExportSaveManager;
use Api\Config\Objects\Export\Gen\RecordActions\AbstractExportCancelAction;
use Api\Config\Objects\Export\SaveManagerContext;
use Api\Gen\Representations\ExportProcedureRepresentationBuilder;
use Api\Gen\Representations\ExportRepresentation;
use Api\Gen\Representations\ExportRepresentationBuilder;
use Api\Objects\ObjectDefinitionCatalog;
use Api\Objects\RecordActions\RecordActionContext;
use Api\Representations\RepresentationBuilderContext;
use ApiMetrics;
use apiTools;
use DateTime;
use Doctrine_Query_Exception;
use Doctrine_Record_Exception;
use Exception;
use piExport;
use stringTools;

class ExportCancelAction extends AbstractExportCancelAction
{
	private ?piExport $piExport = null;
	private ?ExportSaveManager $exportSaveManager = null;

	/**
	 * @param RecordActionContext $recordActionContext
	 * @return ExportRepresentation|null
	 * @throws Doctrine_Query_Exception
	 * @throws Doctrine_Record_Exception
	 * @throws Exception
	 */
	public function executeActionWithArgs(RecordActionContext $recordActionContext): ?ExportRepresentation
	{
		$this->piExport = $this->exportSaveManager->doCancel($this->piExport);

		$objectName = apiTools::getObjectNameFromId($this->piExport->object);

		$builder = (new ExportRepresentationBuilder())
			->setId($this->piExport->id)
			->setStatus($this->piExport->status)
			->setIsExpired($this->piExport->is_expired)
			->setCreatedAt(new DateTime($this->piExport->created_at))
			->setUpdatedAt(new DateTime($this->piExport->updated_at));

		$parameters = json_decode($this->piExport->parameters);
		if (isset($parameters->include_byte_order_mark)) {
			$builder->setIncludeByteOrderMark($parameters->include_byte_order_mark);
		} else {
			$builder->setIncludeByteOrderMark(false);
		}
		if (isset($parameters->max_file_size_bytes)) {
			$builder->setMaxFileSizeBytes($parameters->max_file_size_bytes);
		} else {
			$builder->setMaxFileSizeBytes(null);
		}

		$objectDefinition = ObjectDefinitionCatalog::getInstance()->findObjectDefinitionByObjectType(
			3,
			$recordActionContext->getAccountId(),
			$objectName
		);
		$doctrineQueryModifier = new ExportDoctrineQueryModifier($objectDefinition);

		$fieldsArray = $doctrineQueryModifier->getFieldsValue(
			$objectDefinition,
			json_decode($this->piExport->selected_fields),
			$recordActionContext->getVersion()
		);
		$builder->setFields($fieldsArray);

		$representationBuilderContext = new RepresentationBuilderContext(
			$recordActionContext->getAccountId(),
			$recordActionContext->getVersion()
		);

		$version = $recordActionContext->getVersion();
		// Likely reading a v4 export through v5 endpoint
		if (stringTools::contains($this->piExport->procedure_name, '_')) {
			$version = 3;
		}

		$procedureArray = (new ExportRepresentationHelper())->getProcedureData(
			$version,
			$recordActionContext->getAccountId(),
			$objectName,
			$this->piExport->procedure_arguments,
			$this->piExport->procedure_name
		);

		$builder->setProcedure(ExportProcedureRepresentationBuilder::createFromArray(
			$procedureArray,
			$representationBuilderContext
		));

		return $builder->build();
	}

	/**
	 * @param RecordActionContext $recordActionContext
	 * @return void
	 * @throws Doctrine_Query_Exception
	 * @throws Exception
	 */
	public function validateWithArgs(RecordActionContext $recordActionContext): void
	{
		parent::validateWithArgs($recordActionContext);

		$this->exportSaveManager = new ExportSaveManager(
			SaveManagerContext::fromRecordAction(
				$recordActionContext,
				self::generateMetrics($recordActionContext->getAccountId(), $recordActionContext->getVersion())
			)
		);

		$this->piExport = $this->exportSaveManager->validateCancel($recordActionContext->getRecordId());
	}

	/**
	 * @param $accountId
	 * @param $version
	 * @return ApiMetrics
	 */
	private function generateMetrics($accountId, $version): ApiMetrics
	{
		return new ApiMetrics(
			$accountId,
			$version,
			'export',
			'cancel'
		);
	}
}

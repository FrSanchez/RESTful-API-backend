<?php
namespace Api\Config\Objects\TrackerDomain;

use Api\Config\Objects\TrackerDomain\Gen\Doctrine\AbstractTrackerDomainDoctrineQueryModifier;
use Api\Objects\Doctrine\QueryBuilderNode;
use Api\Objects\FieldDefinition;
use DataTable;
use Doctrine_Record;
use piTrackerDomain;
use piTrackerDomainTable;
use SslBadgeInfo;

class TrackerDomainDoctrineQueryModifier extends AbstractTrackerDomainDoctrineQueryModifier
{
	const SSL_STATUS_COLUMN = "ssl_status";
	const VALIDATION_STATUS_COLUMN = "validation_status";

	protected function modifyQueryWithSslStatusField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot
			->addSelection(self::SSL_STATUS_COLUMN)
			->addSelection(self::VALIDATION_STATUS_COLUMN);
	}

	protected function getValueForSslStatusField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piTrackerDomain $doctrineRecord */
		$badgeInfo = new SslBadgeInfo($doctrineRecord, DataTable::RENDER_PLAIN);
		return strtolower($badgeInfo->renderCustomerFacingBadge());
	}

	protected function modifyQueryWithHttpsStatusField(QueryBuilderNode $queryBuilderRoot, FieldDefinition $fieldDef)
	{
		return $queryBuilderRoot->addSelection(self::SSL_STATUS_COLUMN);
	}

	protected function getValueForHttpsStatusField(Doctrine_Record $doctrineRecord, FieldDefinition $fieldDef)
	{
		/** @var piTrackerDomain $doctrineRecord */
		return strtolower(piTrackerDomainTable::getInstance()->getActiveProtocol($doctrineRecord));
	}
}

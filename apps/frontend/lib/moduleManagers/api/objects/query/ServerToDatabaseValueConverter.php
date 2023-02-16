<?php
namespace Api\Objects\Query;

use DateTime;
use dateTools;
use Singleton;

class ServerToDatabaseValueConverter
{
	use Singleton;

	public function convertServerValueToDatabaseValue($serverValue)
	{
		if ($serverValue instanceof DateTime) {
			$value = dateTools::mysqlFormat($serverValue);
		} else {
			$value = $serverValue;
		}
		return $value;
	}
}

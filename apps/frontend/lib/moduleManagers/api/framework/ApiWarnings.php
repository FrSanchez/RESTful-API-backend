<?php
namespace Api\Framework;

use RuntimeException;

class ApiWarnings
{
	const HTTP_HEADER = 'Pardot-Warning';

	// 1XX codes are reserved for framework level warnings
	const QUERY_PAGE_LIMIT_REACHED = 101;

	// 2XX codes are reserved for record related warnings
	const RECORD_IN_RECYCLE_BIN = 201;
	const RECORD_REDACTED = 202;

	public static function getWarningMessageByCode(int $warningCode)
	{
		switch ($warningCode) {
			case self::QUERY_PAGE_LIMIT_REACHED:
				return 'Record count for nextPageToken sequence has been exceeded. No page token returned.';
			case self::RECORD_IN_RECYCLE_BIN:
				return 'Record is in recycle bin';
			case self::RECORD_REDACTED:
				return 'Record(s) have been redacted from output due to access rules';
			default:
				throw new RuntimeException('Unknown error code specified: ' . $warningCode);
		}
	}

	public static function createWarningMessageForHttpHeader(int ... $warningCodes)
	{
		$warnings = [];
		foreach ($warningCodes as $warningCode) {
			$rawMessage = self::getWarningMessageByCode($warningCode);
			$cleanMessage = $rawMessage;

			// encode commas
			$cleanMessage = str_replace(',', '\,', $cleanMessage);

			// encode semicolons
			$cleanMessage = str_replace(';', '\;', $cleanMessage);

			$warnings[] = "{$warningCode};{$cleanMessage}";
		}
		return join(',', $warnings);
	}
}

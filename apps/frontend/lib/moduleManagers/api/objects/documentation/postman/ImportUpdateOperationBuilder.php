<?php
namespace Api\Objects\Postman;

use stdClass;

class ImportUpdateOperationBuilder extends UpdateOperationBuilder
{
	/**
	 * @param bool $forUpdate
	 * @param bool $hasBinaryAttachment
	 * @return Body
	 */
	public function generateCreateAndUpdateBody(bool $forUpdate = false, bool $hasBinaryAttachment = false): Body
	{
		$input = new stdClass();
		$input->status = "ready";
		$payload = json_encode($input, JSON_PRETTY_PRINT);

		return $this->generateRawBody($payload);
	}
}

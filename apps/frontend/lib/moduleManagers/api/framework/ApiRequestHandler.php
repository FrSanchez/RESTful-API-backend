<?php
namespace Api\Framework;

use apiActions;
use piWebRequest;
use sfWebResponse;

interface ApiRequestHandler
{
	public function handle(apiActions $apiActions, piWebRequest $request, sfWebResponse $response): void;
}

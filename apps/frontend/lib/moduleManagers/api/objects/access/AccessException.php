<?php
namespace Api\Objects\Access;

use RuntimeException;

/**
 * Exception when the access manager fails.
 *
 * NOTE: This does not extend ApiException because it could hold information that is not allowed for the user to see!
 *
 * Class AccessException
 * @package Api\Objects\Access
 */
class AccessException extends RuntimeException
{
}

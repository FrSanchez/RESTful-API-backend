<?php
namespace Api\Objects\Access;

use AbilitiesGroup;
use piUser;

/**
 * Context of the user needing access to an object.
 *
 * Interface AccessContext
 * @package Api\Objects\Access
 */
interface AccessContext
{
	public function getAccountId(): int;

	/**
	 * Gets the ID of the user needing access.
	 * @return int
	 */
	public function getUserId(): int;

	/**
	 * Gets the abilities associated to the user.
	 * @return AbilitiesGroup
	 */
	public function getUserAbilities(): AbilitiesGroup;

	public function getUser();
}

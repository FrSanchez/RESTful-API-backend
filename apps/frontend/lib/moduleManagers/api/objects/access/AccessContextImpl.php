<?php
namespace Api\Objects\Access;

use AbilitiesGroup;
use piUser;

class AccessContextImpl implements AccessContext
{
	/** @var int */
	private $accountId;

	/** @var int */
	private $userId;

	/** @var AbilitiesGroup */
	private $userAbilities;

	/** @var piUser $user */
	private $user;

	public function __construct(
		int $accountId,
		piUser $user,
		AbilitiesGroup $userAbilities
	) {
		$this->accountId = $accountId;
		$this->user = $user;
		$this->userId = $user->id;
		$this->userAbilities = $userAbilities;
	}

	public function getAccountId(): int
	{
		return $this->accountId;
	}

	public function getUserId(): int
	{
		return $this->userId;
	}

	public function getUserAbilities(): AbilitiesGroup
	{
		return $this->userAbilities;
	}

	public function getUser()
	{
		return $this->user;
	}
}

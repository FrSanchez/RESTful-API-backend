<?php
namespace Api\Framework;

use AbilitiesGroup;
use Api\DataTypes\ConversionContext;
use Api\Objects\Access\AccessContext;
use Api\Objects\Access\AccessContextImpl;
use DateTime;
use piUser;

class ApiRequest
{
	private int $accountId;
	private bool $isInternalRequest;
	private piUser $user;
	private AbilitiesGroup $userAbilities;
	private ConversionContext $conversionContext;
	private int $version;
	private ?DateTime $ifModifiedSince;
	private AccessContext $accessContext;
	private string $operationName;

	/** @var ApiParameter[] */
	private array $parameters;

	public function __construct(
		int $accountId,
		piUser $user,
		AbilitiesGroup $userAbilities,
		ConversionContext $conversionContext,
		int $version,
		array $parameters,
		?DateTime $ifModifiedSince,
		string $operationName,
		bool $isInternalRequest = false
	) {
		$this->accountId = $accountId;
		$this->user = clone $user;
		$this->userAbilities = $userAbilities;
		$this->conversionContext = $conversionContext;
		$this->version = $version;
		$this->parameters = $parameters;
		$this->ifModifiedSince = $ifModifiedSince;
		$this->accessContext = new AccessContextImpl($accountId, $user, $userAbilities);
		$this->operationName = $operationName;
		$this->isInternalRequest = $isInternalRequest;
	}

	/**
	 * @return ConversionContext
	 */
	public function getConversionContext(): ConversionContext
	{
		return $this->conversionContext;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * @return int
	 */
	public function getUserId(): int
	{
		return $this->user->id;
	}

	/**
	 * @return piUser
	 */
	public function getUser(): piUser
	{
		return $this->user;
	}

	/**
	 * @return AbilitiesGroup
	 */
	public function getUserAbilities(): AbilitiesGroup
	{
		return $this->accessContext->getUserAbilities();
	}

	/**
	 * Gets the access context associated to the request.
	 * @return AccessContext
	 */
	public function getAccessContext(): AccessContext
	{
		return $this->accessContext;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	public function hasParameter(string $name): bool
	{
		return array_key_exists($name, $this->parameters);
	}

	public function getParameter(string $name)
	{
		return $this->parameters[$name]->getServerValue();
	}

	public function getApiParameter(string $name): ApiParameter
	{
		return $this->parameters[$name];
	}

	/**
	 * Gets the If-Modified-Since value if the user specified one, otherwise returns null.
	 * @return DateTime|null
	 */
	public function getIfModifiedSince(): ?DateTime
	{
		return $this->ifModifiedSince;
	}

	/**
	 * @return string
	 */
	public function getOperationName(): string
	{
		return $this->operationName;
	}

	/**
	 * @return bool
	 */
	public function isInternalRequest(): bool
	{
		return $this->isInternalRequest;
	}
}

<?php

namespace Api\Config\Objects\Export;

use Api\DataTypes\ConversionContext;
use Api\Objects\RecordActions\RecordActionContext;
use apiActions;
use ApiMetrics;
use piUser;

class SaveManagerContext
{
	private piUser $apiUser;
	private int $version;
	private ApiMetrics $metrics;
	private bool $isInternalRequest;
	private ?string $serviceName;
	private int $accountId;
	private ConversionContext $conversionContext;

	/**
	 * @param piUser $apiUser
	 * @param int $version
	 * @param ApiMetrics $metrics
	 * @param bool $isInternalRequest
	 * @param int $accountId
	 * @param string|null $serviceName
	 * @param ConversionContext $conversionContext
	 */
	public function __construct(
		piUser            $apiUser,
		int               $version,
		ApiMetrics        $metrics,
		bool              $isInternalRequest,
		int               $accountId,
		?string           $serviceName,
		ConversionContext $conversionContext)
	{
		$this->apiUser = $apiUser;
		$this->version = $version;
		$this->metrics = $metrics;
		$this->isInternalRequest = $isInternalRequest;
		$this->accountId = $accountId;
		$this->serviceName = $serviceName;
		$this->conversionContext = $conversionContext;
	}

	/**
	 * @return piUser
	 */
	public function getApiUser(): piUser
	{
		return $this->apiUser;
	}

	/**
	 * @return int
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * @return ApiMetrics
	 */
	public function getMetrics(): ApiMetrics
	{
		return $this->metrics;
	}

	/**
	 * @return bool
	 */
	public function isInternalRequest(): bool
	{
		return $this->isInternalRequest;
	}

	/**
	 * @return string|null
	 */
	public function getServiceName(): ?string
	{
		return $this->serviceName;
	}

	/**
	 * @return int
	 */
	public function getAccountId(): int
	{
		return $this->accountId;
	}

	/**
	 * @return ConversionContext
	 */
	public function getConversionContext(): ConversionContext
	{
		return $this->conversionContext;
	}

	/**
	 * @param apiActions $apiActions
	 * @return SaveManagerContext
	 */
	public static function fromApiActions(apiActions $apiActions): SaveManagerContext
	{
		return new self(
			$apiActions->apiUser,
			$apiActions->version,
			$apiActions->getMetrics(),
			$apiActions->isInternalRequest(),
			$apiActions->apiUser->account_id,
			$apiActions->getServiceName(),
			ConversionContext::createFromApiActions($apiActions)
		);
	}

	/**
	 * @param RecordActionContext $recordActionContext
	 * @param ApiMetrics $metrics
	 * @return SaveManagerContext
	 */
	public static function fromRecordAction(RecordActionContext $recordActionContext, ApiMetrics $metrics): SaveManagerContext
	{
		return new self(
			$recordActionContext->getAccessContext()->getUser(),
			$recordActionContext->getVersion(),
			$metrics,
			$recordActionContext->isInternalRequest(),
			$recordActionContext->getAccountId(),
			null,
			ConversionContext::createDefault($recordActionContext->getVersion())
		);
	}
}

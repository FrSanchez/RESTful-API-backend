<?php

namespace Api\Objects\Postman;

use Api\Objects\StaticObjectDefinitionCatalog;
use ApiObjectsRequestRouter;
use Exception;
use FeatureFlagGroup;
use Ramsey\Uuid\Uuid;
use RESTClient;
use RuntimeException;
use sfContext;
use stdClass;

class PostmanGenerator
{
	public const COLLECTION_NAME = "Pardot V5 API Collection";
	public const COLLECTION_DESCRIPTION = "The Pardot APIs collection contains requests for the following Salesforce Pardot APIs:\n\n<table>\n   <tr>\n      <td> V5 Synchronous API</td>\n   </tr>\n</table>\n\n**⚠️ Disclaimers:**\n- This collection is provided as-is. It's not officially supported by Salesforce or covered by SLAs.\n- API documentation is not provided with the collection. Please refer to the [official documentation](https://developer.pardot.com).\n\nℹ️ Report issues and ask questions --add link-- [here](https://github.com/forcedotcom/postman-salesforce-apis/issues).\n\n## Get started\n\nFollow [this documentation](https://github.com/forcedotcom/postman-salesforce-apis) to get started with the collection. --add link--\n\n## Authentication\n\nThe collection leverages by default the Web Server OAuth flow.  Add your connected app's client ID and secret as a collection variable.  Next go to the collection->Auth & request a token.\n\n## Variables Reference\n\nThe collection relies on the following variables:\n\n| Variable | Description |\n| --- | --- |\n| `oauth_domain` | The base login URL for authentication. Either:<br/>- `https://test.salesforce.com` for sandboxes or Scratch orgs<br/>- `https://login.salesforce.com` for production, Trailhead Playground and Developer Edition orgs<br/>- your custom My Domain base URL. |\n| `domain` | The base login URL for authentication. Either:<br/>- 'https://pi.demo.pardot.com' for Pardot Sandboxes and Developer Environments<br/>- 'https://pi.pardot.com' for Pardot Production instances |\n| `userEmail ` | Your Salesforce username. |\n| `userPassword ` | Your Salesforce password. |\n| `clientId ` | Connected App client Id. |\n| `clientSecret ` | Connected App client secret. |\n| `businessUnitId ` | The Pardot Business Unit ID of your specific Pardot instance |";
	public const SCHEMA_NAME = "https://schema.getpostman.com/json/collection/v2.1.0/collection.json";

	public const LOGIN_SCRIPT = [
		"pm.test(\"Status code is 200\", function () {",
		"    pm.response.to.have.status(200);",
		"});",
		"",
		"var jsonObject = null;",
		"",
		"if (responseBody.charAt(0) == '{') {",
		"    jsonObject = JSON.parse(responseBody);",
		"} else {",
		"    jsonObject = xml2Json(responseBody);",
		"    jsonObject = jsonObject.rsp;",
		"}",
		"",
		"postman.setEnvironmentVariable(\"api_key\", jsonObject.api_key);"
	];

	public const TEST_SCRIPT = [
		"pm.test(\"API call should not contain error\", function () {",
		"    pm.response.to.not.be.error;",
		"    pm.response.to.not.have.jsonBody(\"err\");",
		"});",
		"var jsonObject = null;",
		"",
		"if (responseBody.charAt(0) == '{') {",
		"    jsonObject = JSON.parse(responseBody);",
		"} else {",
		"    jsonObject = xml2Json(responseBody);",
		"    jsonObject = jsonObject.rsp;",
		"}",
		"postman.setEnvironmentVariable(\"nextPageToken\", jsonObject.nextPageToken);",
	];

	public const PREREQ_SCRIPT = [
		"pm.request.headers.add({ ",
		"	key: 'Pardot-Business-Unit-Id', ",
		"	name: 'Pardot-Business-Unit-Id', ",
		"	value: pm.environment.get('businessUnitId')",
		"});",
	];

	public const OAUTH_SCRIPT = [
		"var jsonData = JSON.parse(responseBody);",
		"var version = jsonData.version;",
		"",
		"pm.environment.set(\"access_token\", jsonData.access_token);",
		"pm.environment.unset(\"api_key\");",
		"",
	];

	private bool $includePardotLogin = false;
	private bool $includeOAuth = false;
	private int $version = 5;
	private ?ApiObjectsRequestRouter $router;

	private StaticObjectDefinitionCatalog $staticObjectDefinitionCatalog ;
	private FeatureFlagGroup $featureFlagGroup;
	private array $featureFlags;

	/**
	 * @throws Exception
	 */
	public function __construct()
	{
		$this->router = sfContext::getInstance()->getContainer()->get('api.objects.router');
		$this->staticObjectDefinitionCatalog = StaticObjectDefinitionCatalog::getInstance();
		$this->featureFlags = [];
	}

	public function buildObjects($version): array
	{
		$objects = [];
		foreach ($this->staticObjectDefinitionCatalog->getObjectNames() as $objectName) {
			$objectDefinition = $this->staticObjectDefinitionCatalog->findObjectDefinitionByObjectType($objectName);
			if ($this->isAllowedObject($objectDefinition->getType())) {
				$objectBuilder = new PostmanObjectBuilder($objectDefinition, $version, $this->featureFlagGroup);
				$object = $objectBuilder->build();
				if (!empty($object->item)) {
					// avoid adding any object without operations
					$objects[] = $object;
				}
			}
		}
		return $objects;
	}

	/**
	 * @param $objectDefinition
	 * @return bool
	 */
	protected function isAllowedObject($objectDefinition): bool
	{
		return $this->router->isAllowedObject($objectDefinition);
	}
	/**
	 * @return $this
	 */
	public function withPardotLogin(): PostmanGenerator
	{
		$this->includePardotLogin = true;
		return $this;
	}

	/**
	 * @param array $featureFlags
	 * @return $this
	 */
	public function withFeatureFlags(array $featureFlags): PostmanGenerator
	{
		$this->featureFlags = $featureFlags;
		return $this;
	}

	/**
	 * @return $this
	 */
	public function withOauth(): PostmanGenerator
	{
		$this->includeOAuth = true;
		return $this;
	}

	/**
	 * @param int $version
	 * @return $this
	 */
	public function setVersion(int $version): PostmanGenerator
	{
		$this->version = $version;
		return $this;
	}

	/**
	 * Entry point to generate the required document
	 * @param string $filename
	 * @return string
	 * @throws Exception
	 */
	public function build(string $filename): string
	{
		$this->createFeatureFlagGroup();
		$objectEntries = $this->buildObjects($this->version);
		$info = $this->getDocumentInfo(Uuid::uuid4()->toString());

		$auth = null;
		if ($this->includePardotLogin) {
			$objectEntries[] = $this->generateLoginCall();
			$auth = $this->generatePardotAuth();
		}
		if ($this->includeOAuth) {
			$objectEntries[] = $this->addAlternativeAuthRequests();
			$auth = $this->generateBearerAuth();
		}

		$test = $this->generateErrorVerificationTest();
//		$prerequest = $this->generatePrereqTest();

		$collection = $this->createStdClassFromArray([
			"info" => $info,
			"item" => $objectEntries,
			"auth" => $auth,
			"event" => [$test], //[$prerequest, $test],
			"protocolProfileBehavior" => new stdClass(),
			"variable" => $this->addVariables()
		]);
		return $this->saveDocumentToFile($filename, $collection);
	}

	/**
	 * Generate this document's header
	 * @param string $postmanId
	 * @return Info
	 * @throws Exception
	 */
	private function getDocumentInfo(string $postmanId): Info
	{
		return new Info($postmanId, self::COLLECTION_NAME, self::COLLECTION_DESCRIPTION, self::SCHEMA_NAME);
	}

	/**
	 * Adds the login call to the generated output
	 * @return Operation Login operation
	 */
	private function generateLoginCall(): Operation
	{
		$login = OperationFactory::create("Login (DEPRECATED)", RESTClient::METHOD_POST);
		$login->addEvent($this->createEventWithJSScript("test", self::LOGIN_SCRIPT));

		$body = new Body();
		$body->setMode("formdata");
		$email = new Entry("email", "text", "{{email}}");
		$password = new Entry("password", "text", "{{password}}");
		$userKey = new Entry("user_key", "text", "{{user_key}}");
		$body->setFormdata([$email, $password, $userKey]);

		$request = $login->getRequest();
		$request->setBody($body);
		$url = $request->getUrl();
		$url->setPath(["api", "login"]);
		$url->addQuery(new Entry("format", null, "json", "string", false));

		return $login;
	}

	/**
	 * @param string $listenEventName
	 * @param array $scriptBody
	 * @return Event
	 */
	private function createEventWithJSScript(string $listenEventName, array $scriptBody): Event
	{
		$script = new Script();
		$script->setExec($scriptBody);
		$script->setType("text/javascript");

		$event = new Event();
		$event->setListen($listenEventName);
		$event->setScript($script);
		return $event;
	}

	/**
	 * @return stdClass
	 */
	private function generatePardotAuth(): stdClass
	{
		$value = new Entry("value", "string", "Pardot user_key={{user_key}},api_key={{api_key}}");
		$authorization = new Entry("key", "string", "Authorization");
		return $this->createStdClassFromArray(["type" => "apikey", "apikey" => [$value, $authorization]]);
	}

	/**
	 * Wrapper to create a stdClass from a key=>value array
	 * @param array $data
	 * @return stdClass
	 */
	private function createStdClassFromArray(array $data): stdClass
	{
		$node = new stdClass();
		foreach ($data as $key => $value) {
			$node->$key = $value;
		}
		return $node;
	}

	/**
	 * @return Event
	 */
	private function generateErrorVerificationTest(): Event
	{
		return $this->createEventWithJSScript("test", self::TEST_SCRIPT);
	}

	/**
	 * @return Event
	 */
	private function generatePrereqTest(): Event
	{
		return $this->createEventWithJSScript("prerequest", self::PREREQ_SCRIPT);
	}

	/**
	 * @param string $filename
	 * @param mixed $collection
	 * @return string
	 */
	private function saveDocumentToFile(string $filename, $collection): string
	{
		$file = fopen($filename, "w");
		if (!$file) {
			throw new RuntimeException("Couldn't create {$filename} ");
		}
		fwrite($file, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		fclose($file);
		return $filename;
	}

	/**
	 * @return stdClass
	 */
	private function generateBearerAuth(): stdClass
	{
		return $this->createStdClassFromArray(["type" => "bearer", "bearer" => [
			new Entry("token", "string", "{{access_token}}"),
		]]);
	}

	/**
	 * @return stdClass
	 */
	private function generateOAuth(): stdClass
	{
		return $this->createStdClassFromArray(["type" => "oauth2", "oauth" => [
			new Entry("scope", "string", "pardot_api"),
			new Entry("clientId", "string", "{{clientId}}"),
			new Entry("authUrl", "string", "https://{{oauth_domain}}/services/oauth2/authorize"),
			new Entry("useBrowser", "boolean", true),
			new Entry("grant_type", "string", "implicit"),
			new Entry("tokenName", "string", "access_token"),
			new Entry("challengeAlgorithm", "string", "S256"),
			new Entry("addTokenTo", "string", "header"),
			new Entry("client_authentication", "string", "header"),
		]]);
	}

	/**
	 * @return Entry[]
	 */
	private function addVariables()
	{
		return [
			new Entry("access_token", null, ""),
			new Entry("domain", null, "pi.demo.pardot.com"),
			new Entry("oauth_domain", null, "login.salesforce.com"),
			new Entry("grant_type", null, "password"),
			new Entry("userEmail", null, "<Insert Username>"),
			new Entry("userPassword", null, "<Insert Password>"),
			new Entry("businessUnitId", null, "<Insert Pardot Business Unit>"),
			new Entry("client_id", null, "<Insert Client ID>"),
			new Entry("client_secret", null, "<Inser Client Secret>")
		];
	}

	/**
	 * @return stdClass
	 */
	private function addAlternativeAuthRequests(): stdClass
	{
		$call = OperationFactory::create("Salesforce Connected App", RESTClient::METHOD_POST);
		$request = $call->getRequest();
		$url = $request->getUrl();
		$path = ["services", "oauth2", "token"];
		$url->setPath($path);
		$url->setHost(["{{oauth_domain}}"]);
		$body = new Body();
		$body->setMode('formdata');
		$body->setFormdata(
			[
				new Entry("grant_type", "text", "password"),
				new Entry("username", "text", "{{userEmail}}"),
				new Entry("password", "text", "{{userPassword}}"),
				new Entry("client_id", "text", "{{clientId}}"),
				new Entry("client_secret", "text", "{{clientSecret}}")]
		);
		$request->setBody($body);
		$call->addEvent($this->createEventWithJSScript("test", self::OAUTH_SCRIPT));
		$item = new stdClass();
		$item->name = "Salesforce OAuth Request";
		$item->item = [$call];

		$item->description = "An alternative way to authenticate using your username and password.  Update the collection authorization type to leverage \"Bearer\" vs. \"OAuth2.0\" for this login flow to work with this collection.";
		return $item;
	}

	private function createFeatureFlagGroup()
	{
		$this->featureFlagGroup = new PostmanFeatureFlagGroup($this->featureFlags);
	}
}

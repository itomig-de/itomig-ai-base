<?php
/**
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\AnthropicAIEngine;

class AnthropicAIEngineTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * Test that AnthropicAIEngine can be instantiated without errors
	 */
	public function testAnthropicAIEngineInstantiation(): void
	{
		$configuration = [
			'url' => 'https://api.anthropic.com',
			'model' => 'claude-3-5-sonnet-latest',
			'api_key' => 'test-api-key'
		];

		$engine = AnthropicAIEngine::GetEngine($configuration);

		static::assertInstanceOf(AnthropicAIEngine::class, $engine);
		static::assertEquals('AnthropicAI', AnthropicAIEngine::GetEngineName());
	}

	/**
	 * A configuration carrying the full Messages API path must be reduced to its
	 * base URL, because LLPhant appends "/v1/messages" itself. Configurations
	 * written before the LLPhant 1.0.10 upgrade use the full endpoint path.
	 */
	public function testFullMessagesEndpointIsReducedToBaseUrl(): void
	{
		static::assertEquals(
			'https://api.anthropic.com',
			$this->GetConfiguredUrl('https://api.anthropic.com/v1/messages')
		);
	}

	/**
	 * An already-base URL must be left untouched.
	 */
	public function testBaseUrlIsKeptAsIs(): void
	{
		static::assertEquals(
			'https://gateway.example',
			$this->GetConfiguredUrl('https://gateway.example')
		);
	}

	/**
	 * A custom gateway path is preserved; only the Messages API suffix is removed.
	 */
	public function testGatewayPathIsPreserved(): void
	{
		static::assertEquals(
			'https://gateway.example/anthropic',
			$this->GetConfiguredUrl('https://gateway.example/anthropic/v1/messages')
		);
	}

	/**
	 * Trailing slashes must not survive into the base URL, otherwise LLPhant
	 * would request "https://host//v1/messages".
	 */
	public function testTrailingSlashIsRemoved(): void
	{
		static::assertEquals(
			'https://gateway.example',
			$this->GetConfiguredUrl('https://gateway.example/')
		);
	}

	/**
	 * Without a configured URL the engine falls back to the Anthropic base URL
	 * instead of the previous full endpoint, which would now be duplicated.
	 */
	public function testDefaultIsAnthropicBaseUrl(): void
	{
		static::assertEquals(
			'https://api.anthropic.com',
			$this->GetConfiguredUrl(null)
		);
	}

	/**
	 * Builds an engine from the given URL and returns the URL it handed on.
	 *
	 * @param string|null $sConfiguredUrl url as found in ai_engine.configuration
	 * @return string the base URL the engine will request against
	 */
	private function GetConfiguredUrl(?string $sConfiguredUrl): string
	{
		$aConfiguration = [
			'model' => 'claude-3-5-sonnet-latest',
			'api_key' => 'test-api-key',
		];
		if ($sConfiguredUrl !== null) {
			$aConfiguration['url'] = $sConfiguredUrl;
		}

		$oEngine = AnthropicAIEngine::GetEngine($aConfiguration);
		return $this->ReadEngineUrl($oEngine);
	}

	/**
	 * Reads the protected url property of the engine.
	 *
	 * @param AnthropicAIEngine $oEngine engine under test
	 * @return string the configured url
	 */
	private function ReadEngineUrl(AnthropicAIEngine $oEngine): string
	{
		$oProperty = new \ReflectionProperty(AnthropicAIEngine::class, 'url');
		$oProperty->setAccessible(true);
		return (string) $oProperty->getValue($oEngine);
	}
}

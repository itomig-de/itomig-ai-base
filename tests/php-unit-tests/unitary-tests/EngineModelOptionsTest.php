<?php
/**
 * Tests for the model_options pass-through from the iTop configuration into
 * the underlying chat implementations (#86, consolidating #41), and for the
 * dedicated max_tokens setting of the Anthropic engine.
 *
 * The options must reach the chat instances verbatim: nothing is filtered,
 * translated or defaulted, and a malformed configuration is rejected loudly
 * instead of degrading into "no options configured".
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\AnthropicAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\MistralAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\OllamaAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\OpenAIEngine;
use Itomig\iTop\Extension\AIBase\Exception\AIConfigurationException;
use LLPhant\Chat\AnthropicChat;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\MistralAIChat;
use LLPhant\Chat\OllamaChat;
use LLPhant\Chat\OpenAIChat;
use ReflectionMethod;
use ReflectionProperty;

class EngineModelOptionsTest extends ItopTestCase
{
	const MODEL_OPTIONS = array('temperature' => 0.7, 'max_completion_tokens' => 1234);

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * Each engine hands the configured provider parameters verbatim to its
	 * chat instance, which forwards them into the provider request payload.
	 *
	 * The third column names the class that declares the private property on
	 * the chat instance: PHP reflection only finds private properties on the
	 * declaring class, and MistralAIChat inherits its options from OpenAIChat.
	 *
	 * @dataProvider provideEngines
	 */
	public function testModelOptionsReachTheChatInstance(string $sEngineClass, string $sChatClass, string $sPropertyClass): void
	{
		$oEngine = $sEngineClass::GetEngine(array(
			'url' => 'http://127.0.0.1:11434/api/',
			'model' => 'test-model',
			'api_key' => 'test-api-key',
			'model_options' => self::MODEL_OPTIONS,
		));

		$aOptions = $this->getChatModelOptions($oEngine, $sChatClass, $sPropertyClass);

		static::assertSame(self::MODEL_OPTIONS, $aOptions,
			$sEngineClass.' did not pass model_options through verbatim.');
	}

	public function provideEngines(): array
	{
		return array(
			'OpenAI' => array(OpenAIEngine::class, OpenAIChat::class, OpenAIChat::class),
			'Mistral' => array(MistralAIEngine::class, MistralAIChat::class, OpenAIChat::class),
			'Anthropic' => array(AnthropicAIEngine::class, AnthropicChat::class, AnthropicChat::class),
			'Ollama' => array(OllamaAIEngine::class, OllamaChat::class, OllamaChat::class),
		);
	}

	/**
	 * Without the setting the engines construct normally and carry an empty
	 * options map, so existing installations keep their behaviour.
	 */
	public function testUnsetModelOptionsStayEmpty(): void
	{
		$oEngine = OpenAIEngine::GetEngine(array(
			'model' => 'test-model',
			'api_key' => 'test-api-key',
		));

		static::assertSame(array(), $this->getChatModelOptions($oEngine, OpenAIChat::class, OpenAIChat::class));
	}

	/**
	 * A malformed model_options value is a configuration mistake and must be
	 * reported, never silently treated as "no options configured".
	 */
	public function testNonArrayModelOptionsAreRejected(): void
	{
		static::expectException(AIConfigurationException::class);
		OpenAIEngine::GetEngine(array(
			'model' => 'test-model',
			'api_key' => 'test-api-key',
			'model_options' => 'temperature=0.2',
		));
	}

	/**
	 * The Anthropic engine takes max_tokens from the dedicated setting; it is
	 * not passable through model_options, which the request builder overrides
	 * with this value.
	 */
	public function testAnthropicMaxTokensIsConfigurable(): void
	{
		$oEngine = AnthropicAIEngine::GetEngine(array(
			'model' => 'claude-3-5-sonnet-latest',
			'api_key' => 'test-api-key',
			'max_tokens' => 8192,
		));

		static::assertSame(8192, $this->getChatProperty($oEngine, AnthropicChat::class, AnthropicChat::class, 'maxTokens'),
			'max_tokens must reach the Anthropic request.');
	}

	public function testAnthropicMaxTokensDefaultsTo4096(): void
	{
		$oEngine = AnthropicAIEngine::GetEngine(array(
			'model' => 'claude-3-5-sonnet-latest',
			'api_key' => 'test-api-key',
		));

		static::assertSame(4096, $this->getChatProperty($oEngine, AnthropicChat::class, AnthropicChat::class, 'maxTokens'));
	}

	public function testAnthropicMaxTokensRejectsNonNumericValues(): void
	{
		static::expectException(AIConfigurationException::class);
		AnthropicAIEngine::GetEngine(array(
			'model' => 'claude-3-5-sonnet-latest',
			'api_key' => 'test-api-key',
			'max_tokens' => 'lots',
		));
	}

	private function getChatModelOptions(object $oEngine, string $sChatClass, string $sPropertyClass): array
	{
		return $this->getChatProperty($oEngine, $sChatClass, $sPropertyClass, 'modelOptions');
	}

	/**
	 * Builds the chat instance of an engine and reads a private property of
	 * it. The chat classes keep their model options private, so reflection is
	 * the only access that does not change the library under test; PHP finds
	 * private properties only on their declaring class, given by $sPropertyClass.
	 */
	private function getChatProperty(object $oEngine, string $sChatClass, string $sPropertyClass, string $sProperty): mixed
	{
		$oMethod = new ReflectionMethod($oEngine, 'createChatInstance');
		$oMethod->setAccessible(true);
		$oChat = $oMethod->invoke($oEngine);
		static::assertInstanceOf(ChatInterface::class, $oChat);
		static::assertInstanceOf($sChatClass, $oChat);

		$oProp = new ReflectionProperty($sPropertyClass, $sProperty);
		$oProp->setAccessible(true);

		return $oProp->getValue($oChat);
	}
}

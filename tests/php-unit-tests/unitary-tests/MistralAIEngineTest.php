<?php
/**
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\MistralAIEngine;

class MistralAIEngineTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * Test that MistralAIEngine can be instantiated without errors
	 * This verifies that the fix for issue #32 (MistralAIConfig does not exist) is working
	 */
	public function testMistralAIEngineInstantiation(): void
	{
		$configuration = [
			'url' => 'https://api.mistral.ai/v1/chat/completions',
			'model' => 'mistral-large-latest',
			'api_key' => 'test-api-key'
		];

		$engine = MistralAIEngine::GetEngine($configuration);

		static::assertInstanceOf(MistralAIEngine::class, $engine);
		static::assertEquals('MistralAI', MistralAIEngine::GetEngineName());
	}
}

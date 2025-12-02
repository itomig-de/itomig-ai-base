<?php
/**
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\OllamaAIEngine;

class OllamaServiceTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testOllamaServiceGetCompletion(): void // with function mocking, just for example purposes
	{
		$mockOllamaService = $this->createMock(OllamaAIEngine::class);
		$mockOllamaService->method('GetCompletion')
			->with(
				'Translate this sentence : "Hello !"',
				'You are french and you are translating english sentences to French.'
			)
			->willReturn('Bonjour !');

		$sCompletion = $mockOllamaService->GetCompletion(
			'Translate this sentence : "Hello !"',
			'You are french and you are translating english sentences to French.'
		);
		static::assertStringContainsString('Bonjour', $sCompletion);
	}
}
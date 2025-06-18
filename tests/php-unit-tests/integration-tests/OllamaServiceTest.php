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

	public function testOllamaServiceGetCompletion(): void // not consistent test but just for example purposes
	{
		$oOllamaService = new OllamaAIEngine('http://127.0.0.1:11434/api/', '', 'deepseek-r1:1.5b');
		$sCompletion = $oOllamaService->GetCompletion('Translate this sentence : "Hello !"', 'You are french and you are translating english sentences to French.');
		var_dump($sCompletion);
		static::assertStringContainsString('Bonjour', $sCompletion);
	}
}
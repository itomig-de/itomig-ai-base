<?php
/**
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Service\AIService;

class AiBaseServiceTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testGetCompletion(): void
	{
		$oAIEngineInterfaceMock = $this->createMock(iAIEngineInterface::class);
		$oAIEngineInterfaceMock->expects(static::once())
			->method('getCompletion')
			->willReturn('<think>Here is my reasoning</think>Mocked response');

		$oAIService = new AIService($oAIEngineInterfaceMock);
		$sCompletion = $oAIService->GetCompletion('This is a test prompt', 'ok');
		static::assertEquals('Mocked response', $sCompletion); // no more think tag, cleaned up by AIService::GetCompletion
	}
}
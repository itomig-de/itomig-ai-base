<?php

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\GenericAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Exception\AIContextWindowException;
use Itomig\iTop\Extension\AIBase\Exception\AIEngineException;
use Itomig\iTop\Extension\AIBase\Exception\AINetworkException;
use Itomig\iTop\Extension\AIBase\Exception\AIVisionUnsupportedException;
use LLPhant\Chat\ChatInterface;
use LLPhant\Exception\HttpException;

class GenericAIEngineExceptionTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testVisionCapabilityErrorsAreClassifiedSeparately(): void
	{
		$aCases = [
			[404, 'HTTP error from AI engine (404): {"error":{"message":"No endpoints found that support image input","code":404}}'],
			[400, 'No available endpoints support vision input'],
			[400, 'Invalid content type. image_url is only supported by certain models.'],
			[400, 'Image input is only supported for Pixtral models.'],
			[400, 'This model does not support images'],
			[400, 'Fireworks AI model does not support image inputs. Use a Fireworks vision model.'],
			[400, 'The selected model is not multimodal and cannot process visual input.'],
			[400, 'The model does not have vision capability'],
			[400, 'Unsupported image input'],
			[400, 'Image inputs are not compatible with this model'],
			[400, 'Unsupported input type: image'],
		];

		foreach ($aCases as [$iCode, $sMessage]) {
			$oException = $this->buildEngine()->classifyForTest(new HttpException($sMessage, $iCode), true);

			static::assertInstanceOf(AIVisionUnsupportedException::class, $oException, $sMessage);
			static::assertSame($iCode, $oException->getCode());
			static::assertStringContainsString('vision-capable model', $oException->getMessage());
		}
	}

	public function testContextWindowErrorsRemainContextErrors(): void
	{
		$aMessages = [
			'maximum context length exceeded',
			'maximum request size exceeded',
			'Image exceeds the maximum size',
		];

		foreach ($aMessages as $sMessage) {
			$oException = $this->buildEngine()->classifyForTest(new HttpException($sMessage, 400));

			static::assertInstanceOf(AIContextWindowException::class, $oException);
		}
	}

	public function testUnrelatedNotFoundErrorsRemainNetworkErrors(): void
	{
		$oException = $this->buildEngine()->classifyForTest(new HttpException('model endpoint not found', 404));

		static::assertInstanceOf(AINetworkException::class, $oException);
		static::assertNotInstanceOf(AIVisionUnsupportedException::class, $oException);
	}

	public function testImageFormatAndUrlErrorsRemainNetworkErrors(): void
	{
		$aMessages = [
			'Unsupported image format: image/png',
			'Invalid image URL',
		];

		foreach ($aMessages as $sMessage) {
			$oException = $this->buildEngine()->classifyForTest(new HttpException($sMessage, 400));

			static::assertInstanceOf(AINetworkException::class, $oException);
			static::assertNotInstanceOf(AIVisionUnsupportedException::class, $oException);
		}
	}

	private function buildEngine(): GenericAIEngine
	{
		return new class extends GenericAIEngine {
			public function __construct()
			{
				parent::__construct('', '', '');
			}

			public static function GetEngineName(): string
			{
				return 'ExceptionTestEngine';
			}

			public static function GetEngine(array $configuration): iAIEngineInterface
			{
				throw new \LogicException('Not used in this test.');
			}

			public function GetCompletion(string $message, string $systemInstruction = ''): string
			{
				throw new \LogicException('Not used in this test.');
			}

			protected function createChatInstance(): ChatInterface
			{
				throw new \LogicException('Not used in this test.');
			}

			public function classifyForTest(HttpException $oException, bool $bVisionRequest = false): AIEngineException
			{
				return $this->classifyHttpException($oException, $bVisionRequest);
			}
		};
	}
}

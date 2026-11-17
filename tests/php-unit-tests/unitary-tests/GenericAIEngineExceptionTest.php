<?php

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\GenericAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Exception\AIContextWindowException;
use Itomig\iTop\Extension\AIBase\Exception\AIEngineException;
use Itomig\iTop\Extension\AIBase\Exception\AIEngineUnclassifiedException;
use Itomig\iTop\Extension\AIBase\Exception\AIInvalidImageException;
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

	public function testUnrelatedNotFoundErrorsAreUnclassifiedNotNetworkErrors(): void
	{
		$oException = $this->buildEngine()->classifyForTest(new HttpException('model endpoint not found', 404));

		static::assertInstanceOf(AIEngineUnclassifiedException::class, $oException);
		static::assertNotInstanceOf(AINetworkException::class, $oException);
		static::assertNotInstanceOf(AIVisionUnsupportedException::class, $oException);
	}

	public function testImageFormatAndUrlErrorsAreUnclassifiedNotNetworkErrors(): void
	{
		$aMessages = [
			'Unsupported image format: image/png',
			'Invalid image URL',
		];

		foreach ($aMessages as $sMessage) {
			$oException = $this->buildEngine()->classifyForTest(new HttpException($sMessage, 400));

			static::assertInstanceOf(AIEngineUnclassifiedException::class, $oException);
			static::assertNotInstanceOf(AINetworkException::class, $oException);
			static::assertNotInstanceOf(AIVisionUnsupportedException::class, $oException);
		}
	}

	public function testAnyVisionRequestRejectionIsReportedAsVisionError(): void
	{
		// The provider answered, so this is not a transport problem. Even when the
		// message does not match any known pattern, a rejected image request must not
		// be presented to the user as a network error.
		$oException = $this->buildEngine()->classifyForTest(
			new HttpException('HTTP error Anthropic (400): {"type":"error","error":{"type":"invalid_request_error","message":"bad image payload"}}', 400),
			true
		);

		static::assertInstanceOf(AIVisionUnsupportedException::class, $oException);
		static::assertStringContainsString('image request was rejected', $oException->getMessage());
	}

	public function testVisionRequestWithContextOverflowStaysContextError(): void
	{
		// Context overflow keeps priority: it is a real, distinct condition even for a
		// vision request.
		$oException = $this->buildEngine()->classifyForTest(
			new HttpException('maximum context length exceeded', 400),
			true
		);

		static::assertInstanceOf(AIContextWindowException::class, $oException);
	}

	public function testImageCountLimitIsEnforcedBeforeDecoding(): void
	{
		$oEngine = $this->buildEngineWithMaxImages(2);
		$aImages = [
			['data' => 'AAAA', 'media_type' => 'image/png'],
			['data' => 'AAAA', 'media_type' => 'image/png'],
			['data' => 'AAAA', 'media_type' => 'image/png'],
		];

		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('Too many images in one turn: 3 given, at most 2 allowed.');
		$oEngine->NormalizeForTest($aImages);
	}

	public function testImageCountLimitBoundaryIsInclusive(): void
	{
		// Exactly the configured number must pass the count check. The payloads here are
		// intentionally invalid, so the next validation stage rejects them - what matters
		// is that the rejection is not the count limit.
		$oEngine = $this->buildEngineWithMaxImages(2);
		$aImages = [
			['data' => 'AAAA', 'media_type' => 'image/png'],
			['data' => 'AAAA', 'media_type' => 'image/png'],
		];

		try {
			$oEngine->NormalizeForTest($aImages);
			static::fail('Expected the payload validation to reject the dummy data.');
		} catch (AIInvalidImageException $e) {
			static::assertStringNotContainsString('Too many images', $e->getMessage());
		}
	}

	public function testZeroImageLimitDisablesImageInput(): void
	{
		$oEngine = $this->buildEngineWithMaxImages(0);

		$this->expectException(AIVisionUnsupportedException::class);
		$this->expectExceptionMessage('Image input is disabled for this AI engine because max_vision_images is 0.');
		$oEngine->NormalizeForTest([['data' => 'AAAA', 'media_type' => 'image/png']]);
	}

	private function buildEngine(): GenericAIEngine
	{
		return self::buildEngineWithMaxImages(null);
	}

	private function buildEngineWithMaxImages(?int $iMaxVisionImages): GenericAIEngine
	{
		return new class($iMaxVisionImages) extends GenericAIEngine {
			public function __construct(?int $iMaxVisionImages)
			{
				parent::__construct('', '', '', false, $iMaxVisionImages);
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

			public function NormalizeForTest(array $aImages): array
			{
				return $this->NormalizeVisionImages($aImages);
			}
		};
	}
}

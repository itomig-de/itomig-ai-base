<?php
/**
 * Unit tests for provider-specific vision message construction.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Contracts\iAIVisionEngine;
use Itomig\iTop\Extension\AIBase\Engine\AnthropicAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\MistralAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\OllamaAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\OpenAIEngine;
use Itomig\iTop\Extension\AIBase\Exception\AIInvalidImageException;
use LLPhant\Chat\Anthropic\AnthropicVisionMessage;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Vision\VisionMessage;

class VisionEngineMessageTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testAllEnginesImplementVisionAdapter(): void
	{
		$aEngines = [
			OpenAIEngine::GetEngine(['api_key' => 'test-api-key']),
			AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key']),
			MistralAIEngine::GetEngine(['api_key' => 'test-api-key']),
			OllamaAIEngine::GetEngine([]),
		];

		foreach ($aEngines as $oEngine) {
			static::assertInstanceOf(iAIVisionEngine::class, $oEngine);
		}
	}

	public function testVisionSupportIsConfiguredPerEngineInstance(): void
	{
		$aEngineFactories = [
			'OpenAI' => static fn(bool $bSupportsVision) => OpenAIEngine::GetEngine([
				'api_key' => 'test-api-key',
				'supports_vision' => $bSupportsVision,
			]),
			'AnthropicAI' => static fn(bool $bSupportsVision) => AnthropicAIEngine::GetEngine([
				'api_key' => 'test-api-key',
				'supports_vision' => $bSupportsVision,
			]),
			'MistralAI' => static fn(bool $bSupportsVision) => MistralAIEngine::GetEngine([
				'api_key' => 'test-api-key',
				'supports_vision' => $bSupportsVision,
			]),
			'OllamaAI' => static fn(bool $bSupportsVision) => OllamaAIEngine::GetEngine([
				'supports_vision' => $bSupportsVision,
			]),
		];

		foreach ($aEngineFactories as $sEngineName => $oEngineFactory) {
			$oDisabledEngine = $oEngineFactory(false);
			$oEnabledEngine = $oEngineFactory(true);

			static::assertFalse($oDisabledEngine->SupportsVision(), $sEngineName);
			static::assertTrue($oEnabledEngine->SupportsVision(), $sEngineName);
		}
	}

	public function testMistralEngineCreatesOpenAICompatibleVisionMessage(): void
	{
		$sImageData = $this->validImageData('png');
		$oEngine = MistralAIEngine::GetEngine([
			'api_key' => 'test-api-key',
			'supports_vision' => true,
		]);
		$oMessage = $oEngine
			->CreateVisionMessage('Describe this image.', [
				[
					'data' => $sImageData,
					'media_type' => 'image/png',
				],
			]);

		static::assertTrue($oEngine->SupportsVision());
		static::assertInstanceOf(VisionMessage::class, $oMessage);
		static::assertSame(ChatRole::User, $oMessage->role);
		static::assertSame('Describe this image.', $oMessage->content);

		$aSerialized = json_decode(json_encode($oMessage, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		static::assertSame('image_url', $aSerialized['content'][1]['type']);
		static::assertSame(
			'data:image/png;base64,'.$sImageData,
			$aSerialized['content'][1]['image_url']['url']
		);
	}

	public function testOllamaEngineCreatesOpenAICompatibleVisionMessage(): void
	{
		$sImageData = $this->validImageData('webp');
		$oEngine = OllamaAIEngine::GetEngine([
			'model' => 'llava',
			'supports_vision' => true,
		]);
		$oMessage = $oEngine->CreateVisionMessage('Describe this image.', [
			[
				'data' => $sImageData,
				'media_type' => 'image/webp',
			],
		]);

		static::assertTrue($oEngine->SupportsVision());
		static::assertInstanceOf(VisionMessage::class, $oMessage);
		$aSerialized = json_decode(json_encode($oMessage, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		static::assertSame('image_url', $aSerialized['content'][1]['type']);
		static::assertSame(
			'data:image/webp;base64,'.$sImageData,
			$aSerialized['content'][1]['image_url']['url']
		);
		static::assertSame('auto', $aSerialized['content'][1]['image_url']['detail']);
	}

	public function testOpenAIEngineCreatesVisionMessageForAllSupportedImageFormats(): void
	{
		$aImages = [
			[
				'data' => $this->validImageData('png'),
				'media_type' => 'image/png',
			],
			[
				'data' => $this->validImageData('jpeg'),
				'media_type' => 'image/jpeg',
			],
			[
				'data' => $this->validImageData('gif'),
				'media_type' => 'image/gif',
			],
			[
				'data' => $this->validImageData('webp'),
				'media_type' => 'image/webp',
			],
		];

		$oMessage = OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Describe these images.', $aImages);

		static::assertInstanceOf(VisionMessage::class, $oMessage);
		static::assertSame(ChatRole::User, $oMessage->role);
		static::assertSame('Describe these images.', $oMessage->content);
		static::assertCount(4, $oMessage->images);

		$aSerialized = json_decode(json_encode($oMessage, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		static::assertSame('user', $aSerialized['role']);
		static::assertSame([
			'type' => 'text',
			'text' => 'Describe these images.',
		], $aSerialized['content'][0]);

		foreach ($aImages as $iIndex => $aImage) {
			$aImageContent = $aSerialized['content'][$iIndex + 1];
			static::assertSame('image_url', $aImageContent['type']);
			static::assertSame(
				'data:'.$aImage['media_type'].';base64,'.$aImage['data'],
				$aImageContent['image_url']['url']
			);
			static::assertSame('auto', $aImageContent['image_url']['detail']);
		}
	}

	public function testOpenAIEngineRejectsEmptyImageEntries(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('data and media_type are required');

		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Text only.', [
				[],
				['data' => ''],
				['data' => '   ', 'media_type' => 'image/png'],
			]);
	}

	public function testVisionValidationRejectsAnEmptyImageList(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('At least one valid image is required');

		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Describe this image.', []);
	}

	public function testVisionValidationRejectsRemoteImageUrls(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('data is not valid base64');

		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Describe this image.', [
				[
					'data' => 'https://example.com/image.png',
					'media_type' => 'image/png',
				],
			]);
	}

	public function testVisionValidationRejectsOversizedImages(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('exceeds the maximum image size');

		$sOversizedPng = "\x89PNG\x0D\x0A\x1A\x0A".str_repeat(chr(0), 5 * 1024 * 1024);
		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Describe this image.', [
				[
					'data' => base64_encode($sOversizedPng),
					'media_type' => 'image/png',
				],
			]);
	}

	public function testOpenAIEngineRejectsMixedValidAndInvalidImages(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('data and media_type are required');

		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('One valid image.', [
				['data' => ''],
				['data' => $this->validImageData('png'), 'media_type' => 'image/png'],
				[],
			]);
	}

	public function testOpenAIEngineRejectsNonImageBase64Data(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('media_type does not match the image data');

		OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Invalid image.', [
				[
					'data' => base64_encode('this is not an image'),
					'media_type' => 'image/png',
				],
			]);
	}

	public function testAnthropicEngineCreatesVisionMessageWithProviderContentBlocks(): void
	{
		$aImages = [
			[
				'data' => $this->validImageData('png'),
				'media_type' => 'image/png',
			],
			[
				'data' => $this->validImageData('jpeg'),
				'media_type' => 'image/jpeg',
			],
		];

		$oMessage = AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Describe these images.', $aImages);

		static::assertInstanceOf(AnthropicVisionMessage::class, $oMessage);
		static::assertSame(ChatRole::User, $oMessage->role);
		static::assertSame('Describe these images.', $oMessage->content);
		static::assertSame([
			[
				'type' => 'image',
				'source' => [
					'type' => 'base64',
					'media_type' => 'image/png',
					'data' => $aImages[0]['data'],
				],
			],
			[
				'type' => 'image',
				'source' => [
					'type' => 'base64',
					'media_type' => 'image/jpeg',
					'data' => $aImages[1]['data'],
				],
			],
			[
				'type' => 'text',
				'text' => 'Describe these images.',
			],
		], $oMessage->contentsArray);
	}

	public function testAnthropicEngineRejectsInvalidEntriesInsteadOfDroppingThem(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('expected an image object');
		$sImageData = $this->validImageData('png');

		AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Keep the valid image.', [
				'not-an-array',
				[],
				['data' => '', 'media_type' => 'image/png'],
				['data' => $sImageData, 'media_type' => 'image/tiff'],
				['data' => 'not-base64!', 'media_type' => 'image/png'],
				['data' => $sImageData, 'media_type' => 'image/png'],
			]);

	}

	public function testAnthropicEngineRejectsWhenAllImagesAreInvalid(): void
	{
		$this->expectException(AIInvalidImageException::class);
		$this->expectExceptionMessage('expected an image object');

		AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Text only.', [
				'not-an-array',
				['data' => '', 'media_type' => 'image/png'],
				['data' => base64_encode('not an image'), 'media_type' => 'image/tiff'],
				['data' => 'not-base64!', 'media_type' => 'image/png'],
			]);

	}

	private function validImageData(string $sFormat): string
	{
		$sBytes = match ($sFormat) {
			'png' => chr(0x89).'PNG'.chr(13).chr(10).chr(26).chr(10).str_repeat(chr(0), 12),
			'jpeg' => chr(255).chr(216).chr(255).chr(224).str_repeat(chr(0), 12),
			'gif' => 'GIF89a'.str_repeat(chr(0), 12),
			'webp' => 'RIFF'.str_repeat(chr(0), 4).'WEBP'.str_repeat(chr(0), 8),
			default => throw new \InvalidArgumentException('Unsupported test image format.'),
		};

		return base64_encode($sBytes);
	}
}

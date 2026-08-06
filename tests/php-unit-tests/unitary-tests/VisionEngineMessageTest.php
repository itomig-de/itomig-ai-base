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
use LLPhant\Chat\Anthropic\AnthropicVisionMessage;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Message;
use LLPhant\Chat\Vision\VisionMessage;

class VisionEngineMessageTest extends ItopTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testOnlyOpenAIAndAnthropicEnginesAdvertiseVisionSupport(): void
	{
		$oOpenAIEngine = OpenAIEngine::GetEngine(['api_key' => 'test-api-key']);
		$oAnthropicEngine = AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key']);
		$oMistralEngine = MistralAIEngine::GetEngine(['api_key' => 'test-api-key']);
		$oOllamaEngine = OllamaAIEngine::GetEngine([]);

		static::assertInstanceOf(iAIVisionEngine::class, $oOpenAIEngine);
		static::assertInstanceOf(iAIVisionEngine::class, $oAnthropicEngine);
		static::assertNotInstanceOf(iAIVisionEngine::class, $oMistralEngine);
		static::assertNotInstanceOf(iAIVisionEngine::class, $oOllamaEngine);
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
			static::assertSame('high', $aImageContent['image_url']['detail']);
		}
	}

	public function testOpenAIEngineSkipsEmptyImagesAndFallsBackToTextWhenNoneRemain(): void
	{
		$oMessage = OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Text only.', [
				[],
				['data' => ''],
				['data' => '   ', 'media_type' => 'image/png'],
			]);

		static::assertInstanceOf(Message::class, $oMessage);
		static::assertNotInstanceOf(VisionMessage::class, $oMessage);
		static::assertSame(ChatRole::User, $oMessage->role);
		static::assertSame('Text only.', $oMessage->content);
	}

	public function testOpenAIEngineKeepsValidImagesWhenEmptyEntriesAreMixedIn(): void
	{
		$sImageData = $this->validImageData('png');
		$oMessage = OpenAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('One valid image.', [
				['data' => ''],
				['data' => $sImageData, 'media_type' => 'image/png'],
				[],
			]);

		static::assertInstanceOf(VisionMessage::class, $oMessage);
		static::assertCount(1, $oMessage->images);
		$aSerialized = json_decode(json_encode($oMessage, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
		static::assertSame('data:image/png;base64,'.$sImageData, $aSerialized['content'][1]['image_url']['url']);
	}

	public function testOpenAIEngineRejectsNonImageBase64Data(): void
	{
		$this->expectException(\InvalidArgumentException::class);

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

	public function testAnthropicEngineSkipsInvalidEntriesAndKeepsValidImages(): void
	{
		$sImageData = $this->validImageData('png');
		$oMessage = AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Keep the valid image.', [
				'not-an-array',
				[],
				['data' => '', 'media_type' => 'image/png'],
				['data' => $sImageData, 'media_type' => 'image/tiff'],
				['data' => 'not-base64!', 'media_type' => 'image/png'],
				['data' => $sImageData, 'media_type' => 'image/png'],
			]);

		static::assertInstanceOf(AnthropicVisionMessage::class, $oMessage);
		static::assertCount(2, $oMessage->contentsArray);
		static::assertSame('image/png', $oMessage->contentsArray[0]['source']['media_type']);
		static::assertSame($sImageData, $oMessage->contentsArray[0]['source']['data']);
		static::assertSame('text', $oMessage->contentsArray[1]['type']);
	}

	public function testAnthropicEngineFallsBackToTextWhenAllImagesAreInvalid(): void
	{
		$oMessage = AnthropicAIEngine::GetEngine(['api_key' => 'test-api-key'])
			->CreateVisionMessage('Text only.', [
				'not-an-array',
				['data' => '', 'media_type' => 'image/png'],
				['data' => base64_encode('not an image'), 'media_type' => 'image/tiff'],
				['data' => 'not-base64!', 'media_type' => 'image/png'],
			]);

		static::assertInstanceOf(Message::class, $oMessage);
		static::assertNotInstanceOf(AnthropicVisionMessage::class, $oMessage);
		static::assertSame(ChatRole::User, $oMessage->role);
		static::assertSame('Text only.', $oMessage->content);
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

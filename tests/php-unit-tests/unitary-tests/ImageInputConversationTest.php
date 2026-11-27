<?php
/**
 * Unit tests for image input conversation support.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Contracts\iAIVisionEngine;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Exception\AIConfigurationException;
use Itomig\iTop\Extension\AIBase\Service\AIService;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Message;

class ImageInputConversationTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testContinueConversationConvertsUserImagesThroughVisionEngine(): void
	{
		$aImages = [
			[
				'data' => base64_encode('png-bytes'),
				'media_type' => 'image/png',
			],
			[
				'data' => base64_encode('jpeg-bytes'),
				'media_type' => 'image/jpeg',
			],
		];

		$oEngine = new class($this, $aImages) implements iAIEngineInterface, iAIVisionEngine {
			public function __construct(
				private ImageInputConversationTest $oTest,
				private array $aExpectedImages
			) {
			}

			public static function GetEngineName(): string
			{
				return 'VisionTestEngine';
			}

			public static function GetEngine(array $configuration): iAIEngineInterface
			{
				throw new \LogicException('Not used in this test.');
			}

			public function SupportsVision(): bool
			{
				return true;
			}

			public function GetCompletion(string $message, string $systemInstruction = ''): string
			{
				throw new \LogicException('Not used in this test.');
			}

			public function CreateVisionMessage(string $sContent, array $aImages): Message
			{
				$this->oTest::assertSame('Describe these screenshots.', $sContent);
				$this->oTest::assertSame($this->aExpectedImages, $aImages);

				return Message::user('vision:'.$sContent);
			}

			public function GetNextTurn(array $aHistory, array $aTools = []): string|array
			{
				$this->oTest::assertCount(2, $aHistory);
				$this->oTest::assertSame(ChatRole::System, $aHistory[0]->role);
				$this->oTest::assertSame(ChatRole::User, $aHistory[1]->role);
				$this->oTest::assertSame('vision:Describe these screenshots.', $aHistory[1]->content);

				return 'The screenshots show an iTop ticket form.';
			}
		};

		$oAIService = new AIService($oEngine);
		$aResult = $oAIService->ContinueConversation([
			[
				'role' => 'user',
				'content' => 'Describe these screenshots.',
				'images' => $aImages,
			],
		]);

		static::assertSame('The screenshots show an iTop ticket form.', $aResult['response']);
		static::assertSame($aImages, $aResult['history'][0]['images']);
	}

	public function testContinueConversationRejectsImagesWhenVisionIsDisabledByConfiguration(): void
	{
		$oEngine = new class implements iAIEngineInterface, iAIVisionEngine {
			public static function GetEngineName(): string
			{
				return 'NonVisionConfiguredEngine';
			}

			public static function GetEngine(array $configuration): iAIEngineInterface
			{
				throw new \LogicException('Not used in this test.');
			}

			public function SupportsVision(): bool
			{
				return false;
			}

			public function GetCompletion(string $message, string $systemInstruction = ''): string
			{
				throw new \LogicException('Not used in this test.');
			}

			public function CreateVisionMessage(string $sContent, array $aImages): Message
			{
				throw new \LogicException('Vision message creation must not be called.');
			}

			public function GetNextTurn(array $aHistory, array $aTools = []): string|array
			{
				throw new \LogicException('The engine must not be called.');
			}
		};

		$oAIService = new AIService($oEngine);

		$this->expectException(AIConfigurationException::class);
		$this->expectExceptionMessage('Image input is not enabled for the configured AI model.');

		$oAIService->ContinueConversation([
			[
				'role' => 'user',
				'content' => 'Describe this screenshot.',
				'images' => [
					[
						'data' => base64_encode('png-bytes'),
						'media_type' => 'image/png',
					],
				],
			],
		]);
	}

	public function testContinueConversationRejectsImagesWhenEngineDoesNotSupportVision(): void
	{
		$oEngine = $this->createMock(iAIEngineInterface::class);
		$oEngine->expects(static::never())->method('GetNextTurn');

		$oAIService = new AIService($oEngine);

		$this->expectException(AIConfigurationException::class);
		$this->expectExceptionMessage('The configured AI engine does not support image input.');

		$oAIService->ContinueConversation([
			[
				'role' => 'user',
				'content' => 'Describe this screenshot.',
				'images' => [
					[
						'data' => base64_encode('png-bytes'),
						'media_type' => 'image/png',
					],
				],
			],
		]);
	}

	public function testContinueConversationTreatsMissingEmptyAndNonArrayImagesAsText(): void
	{
		$oEngine = $this->createMock(iAIEngineInterface::class);
		$oEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturnCallback(function (array $aHistory, array $aTools): string {
				static::assertCount(4, $aHistory);
				static::assertSame(ChatRole::System, $aHistory[0]->role);
				static::assertSame(ChatRole::User, $aHistory[1]->role);
				static::assertSame('No images', $aHistory[1]->content);
				static::assertSame(ChatRole::User, $aHistory[2]->role);
				static::assertSame('Empty images', $aHistory[2]->content);
				static::assertSame(ChatRole::User, $aHistory[3]->role);
				static::assertSame('Non-array images', $aHistory[3]->content);

				return 'Text-only response';
			});

		$oAIService = new AIService($oEngine);
		$aResult = $oAIService->ContinueConversation([
			[
				'role' => 'user',
				'content' => 'No images',
			],
			[
				'role' => 'user',
				'content' => 'Empty images',
				'images' => [],
			],
			[
				'role' => 'user',
				'content' => 'Non-array images',
				'images' => 'not-an-array',
			],
		]);

		static::assertSame('Text-only response', $aResult['response']);
		static::assertCount(4, $aResult['history']);
	}

	public function testAssistantImagesRemainNormalAssistantMessages(): void
	{
		$oEngine = $this->createMock(iAIEngineInterface::class);
		$oEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturnCallback(function (array $aHistory, array $aTools): string {
				static::assertCount(2, $aHistory);
				static::assertSame(ChatRole::Assistant, $aHistory[1]->role);
				static::assertSame('Assistant image description', $aHistory[1]->content);

				return 'Next response';
			});

		$oAIService = new AIService($oEngine);
		$aResult = $oAIService->ContinueConversation([
			[
				'role' => 'assistant',
				'content' => 'Assistant image description',
				'images' => [
					[
						'data' => base64_encode('ignored'),
						'media_type' => 'image/png',
					],
				],
			],
		]);

		static::assertSame('Next response', $aResult['response']);
	}
}

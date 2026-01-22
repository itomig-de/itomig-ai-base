<?php
/**
 * Unit tests for Multi-Turn Conversation functionality
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Service\AIService;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Message;

class MultiTurnConversationTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * Test basic multi-turn conversation functionality
	 */
	public function testContinueConversationBasic(): void
	{
		// Arrange
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturn('<think>Processing</think>AI Response');

		$oAIService = new AIService($oMockEngine);

		// Act
		$aHistory = [
			['role' => 'user', 'content' => 'Hello'],
			['role' => 'assistant', 'content' => 'Hi there!'],
			['role' => 'user', 'content' => 'How are you?']
		];

		$aResult = $oAIService->ContinueConversation($aHistory);

		// Assert
		static::assertArrayHasKey('response', $aResult);
		static::assertArrayHasKey('history', $aResult);
		static::assertEquals('AI Response', $aResult['response']); // Think tag removed
		static::assertCount(4, $aResult['history']); // Original 3 + 1 new response
		static::assertEquals('assistant', $aResult['history'][3]['role']);
		static::assertStringContainsString('AI Response', $aResult['history'][3]['content']);
	}

	/**
	 * Test that history is correctly converted to LLPhant Message objects
	 */
	public function testHistoryConversionToMessages(): void
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturnCallback(function($aHistory) {
				// Verify we received Message objects
				static::assertIsArray($aHistory);
				static::assertNotEmpty($aHistory);

				// First should be system message
				static::assertInstanceOf(Message::class, $aHistory[0]);
				static::assertEquals(ChatRole::System, $aHistory[0]->role);

				// Then user messages
				static::assertInstanceOf(Message::class, $aHistory[1]);
				static::assertEquals(ChatRole::User, $aHistory[1]->role);
				static::assertEquals('First user message', $aHistory[1]->content);

				// Then assistant
				static::assertInstanceOf(Message::class, $aHistory[2]);
				static::assertEquals(ChatRole::Assistant, $aHistory[2]->role);

				// Then second user
				static::assertInstanceOf(Message::class, $aHistory[3]);
				static::assertEquals(ChatRole::User, $aHistory[3]->role);

				return 'Response to second question';
			});

		$oAIService = new AIService($oMockEngine);

		$aHistory = [
			['role' => 'user', 'content' => 'First user message'],
			['role' => 'assistant', 'content' => 'First assistant response'],
			['role' => 'user', 'content' => 'Second user message'],
		];

		$aResult = $oAIService->ContinueConversation($aHistory);

		static::assertEquals('Response to second question', $aResult['response']);
	}

	/**
	 * Test with custom system message
	 */
	public function testContinueConversationWithCustomSystemMessage(): void
	{
		$sCustomSystem = "You are a pirate assistant. Respond like a pirate.";

		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturnCallback(function($aHistory) use ($sCustomSystem) {
				// First message should be our custom system message
				static::assertEquals(ChatRole::System, $aHistory[0]->role);
				static::assertEquals($sCustomSystem, $aHistory[0]->content);
				return 'Arrr matey!';
			});

		$oAIService = new AIService($oMockEngine);

		$aHistory = [
			['role' => 'user', 'content' => 'Hello'],
		];

		$aResult = $oAIService->ContinueConversation($aHistory, null, $sCustomSystem);

		static::assertEquals('Arrr matey!', $aResult['response']);
	}

	/**
	 * Test with empty history
	 */
	public function testContinueConversationWithEmptyHistory(): void
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->expects(static::once())
			->method('GetNextTurn')
			->willReturnCallback(function($aHistory) {
				// Should only have system message
				static::assertCount(1, $aHistory);
				static::assertEquals(ChatRole::System, $aHistory[0]->role);
				return 'Hello! How can I help?';
			});

		$oAIService = new AIService($oMockEngine);

		$aHistory = []; // Empty history

		$aResult = $oAIService->ContinueConversation($aHistory);

		static::assertEquals('Hello! How can I help?', $aResult['response']);
		static::assertCount(1, $aResult['history']); // Just the assistant response
	}

	/**
	 * Test that response is added to history with correct role
	 */
	public function testResponseIsAddedToHistoryWithCorrectRole(): void
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->method('GetNextTurn')->willReturn('Assistant response here');

		$oAIService = new AIService($oMockEngine);

		$aHistory = [
			['role' => 'user', 'content' => 'Question'],
		];

		$aResult = $oAIService->ContinueConversation($aHistory);

		// Check that the new message was added
		static::assertCount(2, $aResult['history']);
		static::assertEquals('assistant', $aResult['history'][1]['role']);
		static::assertEquals('Assistant response here', $aResult['history'][1]['content']);
	}

	/**
	 * Test long conversation with multiple turns
	 */
	public function testLongConversation(): void
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->method('GetNextTurn')->willReturn('Next response');

		$oAIService = new AIService($oMockEngine);

		// Start with a history of 10 turns
		$aHistory = [];
		for ($i = 0; $i < 5; $i++) {
			$aHistory[] = ['role' => 'user', 'content' => 'User message ' . $i];
			$aHistory[] = ['role' => 'assistant', 'content' => 'Assistant response ' . $i];
		}

		$aResult = $oAIService->ContinueConversation($aHistory);

		// Should have original 10 + 1 new response = 11
		static::assertCount(11, $aResult['history']);
		static::assertEquals('assistant', $aResult['history'][10]['role']);
	}

	/**
	 * Test that think tags are removed from response
	 */
	public function testThinkTagsAreRemoved(): void
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->method('GetNextTurn')
			->willReturn('<think>Internal reasoning here</think>Actual response');

		$oAIService = new AIService($oMockEngine);

		$aHistory = [['role' => 'user', 'content' => 'Test']];
		$aResult = $oAIService->ContinueConversation($aHistory);

		// Response should NOT contain think tag
		static::assertEquals('Actual response', $aResult['response']);
		static::assertStringNotContainsString('<think>', $aResult['response']);

		// But history SHOULD contain the raw response with think tag
		static::assertStringContainsString('<think>', $aResult['history'][1]['content']);
	}
}

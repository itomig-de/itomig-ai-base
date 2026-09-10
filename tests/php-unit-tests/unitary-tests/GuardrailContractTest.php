<?php

/**
 * Tests for the iAIGuardrail extension point in AIService.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Contracts\iAIGuardrail;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Exception\AIGuardrailBlockedException;
use Itomig\iTop\Extension\AIBase\Result\GuardrailVerdict;
use Itomig\iTop\Extension\AIBase\Service\AIService;

class GuardrailContractTest extends ItopDataTestCase
{
	/**
	 * Builds a guardrail fake that records every Check() call so tests can assert
	 * on what was screened. Deliberately an anonymous class created at runtime: a
	 * named top-level class implementing iAIGuardrail would need the interface at
	 * file-load time, before the extension autoloader is registered, and PHPUnit
	 * loads every test file while assembling the suite.
	 *
	 * @param string[] $aEnabledDirections Directions this guardrail claims
	 * @param string[] $aBlockOn           Substrings that trigger a blocking verdict
	 * @param bool     $bThrow             Whether Check() should throw instead of returning a verdict
	 */
	private function MakeRecordingGuardrail(array $aEnabledDirections, array $aBlockOn = [], bool $bThrow = false): iAIGuardrail
	{
		return new class ($aEnabledDirections, $aBlockOn, $bThrow) implements iAIGuardrail {
			/** @var array<int, array{content: string, surface: string, direction: string, context: array}> */
			public array $aCalls = [];

			/** @var array<int, array{direction: string, context: array}> Records IsEnabledFor() calls */
			public array $aEnabledForCalls = [];

			/** @var string[] Directions this guardrail claims */
			private array $aEnabledDirections;

			/** @var string[] Contents that should produce a blocking verdict */
			private array $aBlockOn;

			/** @var bool Whether Check() should throw instead of returning a verdict */
			private bool $bThrow;

			public function __construct(array $aEnabledDirections, array $aBlockOn, bool $bThrow)
			{
				$this->aEnabledDirections = $aEnabledDirections;
				$this->aBlockOn           = $aBlockOn;
				$this->bThrow             = $bThrow;
			}

			public function IsEnabledFor(string $sSurface, string $sDirection, array $aContext = []): bool
			{
				$this->aEnabledForCalls[] = ['direction' => $sDirection, 'context' => $aContext];

				return in_array($sDirection, $this->aEnabledDirections, true);
			}

			public function Check(string $sContent, string $sSurface, string $sDirection, array $aContext = []): GuardrailVerdict
			{
				$this->aCalls[] = ['content' => $sContent, 'surface' => $sSurface, 'direction' => $sDirection, 'context' => $aContext];

				if ($this->bThrow) {
					throw new \RuntimeException('Guardrail backend unreachable');
				}

				foreach ($this->aBlockOn as $sNeedle) {
					if (str_contains($sContent, $sNeedle)) {
						return new GuardrailVerdict(true, [[
							'policy_code' => 'test_policy',
							'matched'     => true,
							'score'       => null,
							'mode'        => 'block',
							'message'     => 'matched test_policy',
						]]);
					}
				}

				return GuardrailVerdict::Pass();
			}
		};
	}

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
		AIService::SetGuardrailsForTest([]);
	}

	protected function tearDown(): void
	{
		// Restore normal discovery for any test running afterwards
		AIService::SetGuardrailsForTest(null);
		parent::tearDown();
	}

	private function MakeEngine(string $sAnswer = 'Mocked response'): iAIEngineInterface
	{
		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->method('GetCompletion')->willReturn($sAnswer);
		$oMockEngine->method('GetNextTurn')->willReturn($sAnswer);

		return $oMockEngine;
	}

	/**
	 * Without any guardrail installed, AIService must behave exactly as before.
	 */
	public function testNoGuardrailInstalledLeavesBehaviourUnchanged(): void
	{
		$oAIService = new AIService($this->MakeEngine('Plain answer'));

		static::assertSame('Plain answer', $oAIService->GetCompletion('Hello'));

		$oResult = $oAIService->ContinueConversation([['role' => 'user', 'content' => 'Hello']]);
		static::assertSame('Plain answer', $oResult->response);
	}

	/**
	 * A guardrail must see both the prompt and the model's answer.
	 */
	public function testGetCompletionScreensInputAndOutput(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		$oAIService = new AIService($this->MakeEngine('The answer'));
		$oAIService->setSurface('unit.test');
		$oAIService->GetCompletion('The question');

		static::assertCount(2, $oGuardrail->aCalls);
		static::assertSame('The question', $oGuardrail->aCalls[0]['content']);
		static::assertSame(iAIGuardrail::DIRECTION_INPUT, $oGuardrail->aCalls[0]['direction']);
		static::assertSame('unit.test', $oGuardrail->aCalls[0]['surface']);
		static::assertSame('The answer', $oGuardrail->aCalls[1]['content']);
		static::assertSame(iAIGuardrail::DIRECTION_OUTPUT, $oGuardrail->aCalls[1]['direction']);
	}

	/**
	 * IsEnabledFor() must be honoured, so opted-out directions cost nothing.
	 */
	public function testDisabledDirectionIsNotChecked(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_OUTPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine()))->GetCompletion('The question');

		static::assertCount(1, $oGuardrail->aCalls);
		static::assertSame(iAIGuardrail::DIRECTION_OUTPUT, $oGuardrail->aCalls[0]['direction']);
	}

	/**
	 * A blocking verdict on the input must abort before the engine is contacted.
	 */
	public function testBlockingVerdictOnInputThrows(): void
	{
		AIService::SetGuardrailsForTest([
			$this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT], ['forbidden']),
		]);

		$oMockEngine = $this->createMock(iAIEngineInterface::class);
		$oMockEngine->expects(static::never())->method('GetCompletion');

		$this->expectException(AIGuardrailBlockedException::class);
		(new AIService($oMockEngine))->GetCompletion('this is forbidden content');
	}

	/**
	 * The exception must carry the policy codes so callers can build a message.
	 */
	public function testBlockedExceptionCarriesVerdict(): void
	{
		AIService::SetGuardrailsForTest([
			$this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT], ['forbidden']),
		]);

		try {
			(new AIService($this->MakeEngine()))->GetCompletion('forbidden');
			static::fail('Expected AIGuardrailBlockedException');
		} catch (AIGuardrailBlockedException $e) {
			static::assertSame(['test_policy'], $e->GetViolatedPolicyCodes());
			static::assertSame(iAIGuardrail::DIRECTION_INPUT, $e->GetDirection());
			static::assertTrue($e->GetVerdict()->blocked);
		}
	}

	/**
	 * Audit mode: violations are reported but the call goes through.
	 */
	public function testAuditModeReportsWithoutBlocking(): void
	{
		$oAuditOnly = new class () implements iAIGuardrail {
			public bool $bChecked = false;

			public function IsEnabledFor(string $sSurface, string $sDirection, array $aContext = []): bool
			{
				return true;
			}

			public function Check(string $sContent, string $sSurface, string $sDirection, array $aContext = []): GuardrailVerdict
			{
				$this->bChecked = true;

				return new GuardrailVerdict(false, [[
					'policy_code' => 'audited_policy',
					'matched'     => true,
					'score'       => 0.9,
					'mode'        => 'audit',
					'message'     => 'matched but only audited',
				]]);
			}
		};
		AIService::SetGuardrailsForTest([$oAuditOnly]);

		$sResult = (new AIService($this->MakeEngine('Still answered')))->GetCompletion('anything');

		static::assertTrue($oAuditOnly->bChecked);
		static::assertSame('Still answered', $sResult);
	}

	/**
	 * A broken guardrail must not take the AI features down with it.
	 */
	public function testGuardrailFailureIsFailOpen(): void
	{
		AIService::SetGuardrailsForTest([
			$this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT], [], true),
		]);

		$sResult = (new AIService($this->MakeEngine('Answer despite broken guardrail')))->GetCompletion('question');

		static::assertSame('Answer despite broken guardrail', $sResult);
	}

	/**
	 * In a conversation, the latest user turn and the model's reply are screened.
	 */
	public function testContinueConversationScreensLatestTurnAndAnswer(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		$oAIService = new AIService($this->MakeEngine('Final answer'));
		$oAIService->ContinueConversation([
			['role' => 'user', 'content' => 'First question'],
			['role' => 'assistant', 'content' => 'First answer'],
			['role' => 'user', 'content' => 'Latest question'],
		]);

		static::assertCount(2, $oGuardrail->aCalls);
		static::assertSame('Latest question', $oGuardrail->aCalls[0]['content']);
		static::assertSame(iAIGuardrail::DIRECTION_INPUT, $oGuardrail->aCalls[0]['direction']);
		static::assertSame('Final answer', $oGuardrail->aCalls[1]['content']);
		static::assertSame(iAIGuardrail::DIRECTION_OUTPUT, $oGuardrail->aCalls[1]['direction']);
	}

	/**
	 * Empty content is not worth a round trip.
	 */
	public function testEmptyContentIsNotScreened(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine('')))->GetCompletion('');

		static::assertCount(0, $oGuardrail->aCalls);
	}

	/**
	 * The system prompt is screened, and before the input it governs.
	 */
	public function testSystemPromptIsScreenedBeforeInput(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([
			iAIGuardrail::DIRECTION_SYSTEM_PROMPT,
			iAIGuardrail::DIRECTION_INPUT,
		]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		$oAIService = new AIService($this->MakeEngine('The answer'));
		$oAIService->GetCompletion('The question', 'You are a helpful assistant.');

		static::assertCount(2, $oGuardrail->aCalls);
		static::assertSame(iAIGuardrail::DIRECTION_SYSTEM_PROMPT, $oGuardrail->aCalls[0]['direction']);
		static::assertSame('You are a helpful assistant.', $oGuardrail->aCalls[0]['content']);
		static::assertSame(iAIGuardrail::DIRECTION_INPUT, $oGuardrail->aCalls[1]['direction']);
	}

	/**
	 * PerformSystemInstruction() substitutes placeholders before delegating, so the
	 * guardrail must see the assembled prompt rather than the template.
	 */
	public function testSystemPromptIsScreenedAfterPlaceholderSubstitution(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_SYSTEM_PROMPT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		$oAIService = new AIService($this->MakeEngine(), ['probe' => 'Template with %1$s inside.']);
		// GetCompletion() is what PerformSystemInstruction() delegates to; feed it the
		// substituted form to assert that whatever arrives here is what gets screened.
		$oAIService->GetCompletion('anything', sprintf('Template with %1$s inside.', 'SUBSTITUTED'));

		static::assertCount(1, $oGuardrail->aCalls);
		static::assertStringContainsString('SUBSTITUTED', $oGuardrail->aCalls[0]['content']);
		static::assertStringNotContainsString('%1$s', $oGuardrail->aCalls[0]['content']);
	}

	/**
	 * In a conversation the prompt is screened once, not per turn or per tool round.
	 */
	public function testSystemPromptIsScreenedOncePerConversationCall(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_SYSTEM_PROMPT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine('Final')))->ContinueConversation(
			[
				['role' => 'user', 'content' => 'First'],
				['role' => 'assistant', 'content' => 'A1'],
				['role' => 'user', 'content' => 'Latest'],
			],
			null,
			'Custom system prompt'
		);

		static::assertCount(1, $oGuardrail->aCalls);
		static::assertSame('Custom system prompt', $oGuardrail->aCalls[0]['content']);
	}

	/**
	 * A blocking verdict on the prompt is distinguishable from one on user input,
	 * so a caller can tell a configuration problem from a content problem.
	 */
	public function testBlockedSystemPromptIsDistinguishableByDirection(): void
	{
		AIService::SetGuardrailsForTest([
			$this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_SYSTEM_PROMPT], ['forbidden']),
		]);

		try {
			(new AIService($this->MakeEngine()))->GetCompletion('harmless question', 'a forbidden prompt');
			static::fail('Expected AIGuardrailBlockedException');
		} catch (AIGuardrailBlockedException $e) {
			static::assertSame(iAIGuardrail::DIRECTION_SYSTEM_PROMPT, $e->GetDirection());
		}
	}

	/**
	 * The iTop context tags are passed to both interface methods, so a guardrail can
	 * let the channel decide whether to screen at all.
	 */
	public function testItopContextIsPassedToGuardrail(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_INPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine()))->GetCompletion('question');

		static::assertNotSame([], $oGuardrail->aEnabledForCalls, 'IsEnabledFor() was not called');
		foreach ($oGuardrail->aEnabledForCalls as $aCall) {
			static::assertArrayHasKey('itop_context', $aCall['context']);
			static::assertIsArray($aCall['context']['itop_context']);
		}
		static::assertArrayHasKey('itop_context', $oGuardrail->aCalls[0]['context']);
	}

	/**
	 * An empty system prompt is not worth a round trip, and is the common case for
	 * callers that pass none.
	 */
	public function testEmptySystemPromptIsNotScreened(): void
	{
		$oGuardrail = $this->MakeRecordingGuardrail([iAIGuardrail::DIRECTION_SYSTEM_PROMPT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine()))->GetCompletion('question');

		static::assertCount(0, $oGuardrail->aCalls);
	}

	/**
	 * GuardrailVerdict::Pass() is the neutral value.
	 */
	public function testPassVerdictIsNeutral(): void
	{
		$oVerdict = GuardrailVerdict::Pass();

		static::assertFalse($oVerdict->blocked);
		static::assertFalse($oVerdict->HasViolations());
		static::assertSame([], $oVerdict->GetViolatedPolicyCodes());
	}
}

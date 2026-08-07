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

/**
 * Records every Check() call so tests can assert on what was screened.
 */
class RecordingGuardrail implements iAIGuardrail
{
	/** @var array<int, array{content: string, surface: string, direction: string}> */
	public array $aCalls = [];

	/** @var string[] Directions this guardrail claims */
	private array $aEnabledDirections;

	/** @var string[] Contents that should produce a blocking verdict */
	private array $aBlockOn;

	/** @var bool Whether Check() should throw instead of returning a verdict */
	private bool $bThrow;

	/**
	 * @param string[] $aEnabledDirections
	 * @param string[] $aBlockOn Substrings that trigger a blocking verdict
	 */
	public function __construct(array $aEnabledDirections, array $aBlockOn = [], bool $bThrow = false)
	{
		$this->aEnabledDirections = $aEnabledDirections;
		$this->aBlockOn           = $aBlockOn;
		$this->bThrow             = $bThrow;
	}

	public function IsEnabledFor(string $sSurface, string $sDirection): bool
	{
		return in_array($sDirection, $this->aEnabledDirections, true);
	}

	public function Check(string $sContent, string $sSurface, string $sDirection, array $aContext = []): GuardrailVerdict
	{
		$this->aCalls[] = ['content' => $sContent, 'surface' => $sSurface, 'direction' => $sDirection];

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
}

class GuardrailContractTest extends ItopDataTestCase
{
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
		$oGuardrail = new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
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
		$oGuardrail = new RecordingGuardrail([iAIGuardrail::DIRECTION_OUTPUT]);
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
			new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT], ['forbidden']),
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
			new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT], ['forbidden']),
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
		$oAuditOnly = new class implements iAIGuardrail {
			public bool $bChecked = false;

			public function IsEnabledFor(string $sSurface, string $sDirection): bool
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
			new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT], [], true),
		]);

		$sResult = (new AIService($this->MakeEngine('Answer despite broken guardrail')))->GetCompletion('question');

		static::assertSame('Answer despite broken guardrail', $sResult);
	}

	/**
	 * In a conversation, the latest user turn and the model's reply are screened.
	 */
	public function testContinueConversationScreensLatestTurnAndAnswer(): void
	{
		$oGuardrail = new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
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
		$oGuardrail = new RecordingGuardrail([iAIGuardrail::DIRECTION_INPUT, iAIGuardrail::DIRECTION_OUTPUT]);
		AIService::SetGuardrailsForTest([$oGuardrail]);

		(new AIService($this->MakeEngine('')))->GetCompletion('');

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

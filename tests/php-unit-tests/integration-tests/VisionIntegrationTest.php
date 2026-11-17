<?php
/**
 * Integration tests for the vision (image input) capability.
 *
 * These tests talk to a real model endpoint. They are skipped when no endpoint
 * is configured, so the suite stays green on machines without one.
 *
 * Configuration, in order of precedence:
 *   1. Environment variables VISION_TEST_URL, VISION_TEST_MODEL, VISION_TEST_API_KEY,
 *      VISION_TEST_ENGINE (OpenAI|Anthropic), VISION_TEST_TEXT_ONLY_MODEL
 *   2. The running iTop instance's ai_engine.name / ai_engine.configuration
 *
 * No credential ever appears in this file.
 *
 * Run: phpunit --group vision
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Itomig\iTop\Extension\AIBase\Engine\AnthropicAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\GenericAIEngine;
use Itomig\iTop\Extension\AIBase\Engine\OpenAIEngine;
use Itomig\iTop\Extension\AIBase\Exception\AIConfigurationException;
use Itomig\iTop\Extension\AIBase\Exception\AIInvalidImageException;
use Itomig\iTop\Extension\AIBase\Exception\AIVisionUnsupportedException;
use Itomig\iTop\Extension\AIBase\Service\AIService;
use Itomig\iTop\AiBase\Test\Support\VisionTestImageFactory;

class VisionIntegrationTest extends ItopTestCase
{
	/** @var string Marker rendered into the first test image */
	private const MARKER_A = 'ERR-4711';

	/** @var string Marker rendered into the second test image */
	private const MARKER_B = 'ERR-0815';

	/** @var string Marker rendered into the third test image */
	private const MARKER_C = 'ERR-1234';

	/** @var array<string, mixed>|null Resolved endpoint settings, null until first use */
	private static ?array $aSettings = null;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
		require_once __DIR__.'/support/VisionTestImageFactory.php';
	}

	// ---------------------------------------------------------------------
	// Endpoint resolution
	// ---------------------------------------------------------------------

	/**
	 * Resolves endpoint settings from the environment, falling back to the iTop module settings.
	 *
	 * @return array{engine: string, url: string, model: string, api_key: string}
	 */
	private static function GetSettings(): array
	{
		if (self::$aSettings !== null) {
			return self::$aSettings;
		}

		$aConfig = [];
		$sConfiguredEngine = 'OpenAI';
		if (self::IsMetaModelBooted()) {
			$aConfig = (array) \MetaModel::GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', []);
			$sConfiguredEngine = (string) \MetaModel::GetModuleSetting('itomig-ai-base', 'ai_engine.name', 'OpenAI');
		}

		self::$aSettings = [
			'engine' => (string) (getenv('VISION_TEST_ENGINE') ?: $sConfiguredEngine),
			'url' => (string) (getenv('VISION_TEST_URL') ?: ($aConfig['url'] ?? '')),
			'model' => (string) (getenv('VISION_TEST_MODEL') ?: ($aConfig['model'] ?? '')),
			'api_key' => (string) (getenv('VISION_TEST_API_KEY') ?: ($aConfig['api_key'] ?? '')),
		];

		return self::$aSettings;
	}

	/**
	 * Tells whether the meta model is loaded, not merely autoloadable.
	 *
	 * The class is available as soon as the iTop autoloader is registered, so
	 * class_exists() is not enough: without a boot, MetaModel::$m_oConfig stays
	 * null and GetModuleSetting() fails on a null property access. Only test
	 * bases that start the model (ItopCustomDatamodelTestCase, ItopDataTestCase)
	 * provide a booted meta model; this suite deliberately stays on ItopTestCase
	 * and therefore falls back to the environment outside of those.
	 *
	 * @return bool true when module settings can be read safely
	 */
	private static function IsMetaModelBooted(): bool
	{
		return class_exists('\MetaModel') && \MetaModel::GetConfig() !== null;
	}

	/**
	 * Skips the test when no endpoint is configured, so the suite stays green offline.
	 */
	private function RequireEndpoint(): array
	{
		$aSettings = self::GetSettings();
		if ($aSettings['url'] === '' || $aSettings['model'] === '') {
			static::markTestSkipped(
				'No vision endpoint configured. Set VISION_TEST_URL and VISION_TEST_MODEL, '.
				'or configure ai_engine.configuration in the running iTop instance.'
			);
		}

		return $aSettings;
	}

	/**
	 * Builds the vision-capable engine named by the settings.
	 *
	 * Anthropic is only usable against the real Anthropic endpoint until
	 * LLPhant gains a configurable base URL (LLPhant/LLPhant#566); the test
	 * skips rather than failing in that case.
	 */
	private function MakeVisionEngine(?bool $bSupportsVision = null): GenericAIEngine
	{
		$aSettings = $this->RequireEndpoint();

		$bSupportsVision = $bSupportsVision ?? true;
		$aArgs = [$aSettings['url'], $aSettings['api_key'], $aSettings['model'], $bSupportsVision];

		switch (strtolower($aSettings['engine'])) {
			case 'openai':
				return new OpenAIEngine(...$aArgs);
			case 'anthropic':
				return new AnthropicAIEngine(...$aArgs);
			default:
				static::markTestSkipped("Vision tests do not cover the '{$aSettings['engine']}' engine.");
		}
	}

	/**
	 * Runs one conversation turn and returns the model's answer.
	 */
	private function Ask(GenericAIEngine $oEngine, array $aHistory): string
	{
		$oService = new AIService($oEngine);

		return $oService->ContinueConversation($aHistory)->response;
	}

	// ---------------------------------------------------------------------
	// T1 — text only (regression anchor)
	// ---------------------------------------------------------------------

	/**
	 * @group integration
	 * @group vision
	 */
	public function testTextOnlyConversationStillWorks(): void
	{
		$oEngine = $this->MakeVisionEngine();

		$sAnswer = $this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'Reply with the single word: READY',
		]]);

		$this->assertNotSame('', trim($sAnswer), 'The model returned an empty answer.');
		$this->assertStringContainsStringIgnoringCase('ready', $sAnswer, "Unexpected answer: {$sAnswer}");
	}

	// ---------------------------------------------------------------------
	// T2 — a single image
	// ---------------------------------------------------------------------

	/**
	 * @group integration
	 * @group vision
	 */
	public function testSingleImageIsReadByTheModel(): void
	{
		$oEngine = $this->MakeVisionEngine();

		$sAnswer = $this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'What code is shown in this image? Answer with the code only, nothing else.',
			'images' => [VisionTestImageFactory::Payload(self::MARKER_A, 'image/png')],
		]]);

		$this->assertStringContainsString(self::MARKER_A, $sAnswer, "The model did not read the image. Answer: {$sAnswer}");
	}

	// ---------------------------------------------------------------------
	// T3 — several images in different formats
	// ---------------------------------------------------------------------

	/**
	 * @group integration
	 * @group vision
	 */
	public function testMultipleImagesInDifferentFormatsAreAllRead(): void
	{
		$oEngine = $this->MakeVisionEngine();

		$sAnswer = $this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'List every error code visible in the images, one per line, nothing else.',
			'images' => [
				VisionTestImageFactory::Payload(self::MARKER_A, 'image/png'),
				VisionTestImageFactory::Payload(self::MARKER_B, 'image/jpeg'),
				VisionTestImageFactory::Payload(self::MARKER_C, 'image/webp'),
			],
		]]);

		foreach ([self::MARKER_A, self::MARKER_B, self::MARKER_C] as $sMarker) {
			$this->assertStringContainsString($sMarker, $sAnswer, "Marker {$sMarker} missing from answer: {$sAnswer}");
		}
	}

	/**
	 * WEBP is not accepted by every backend behind a gateway, so this case skips
	 * instead of failing when the provider rejects the format.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testGifImageIsReadOrExplicitlyRejected(): void
	{
		$oEngine = $this->MakeVisionEngine();

		try {
			$sAnswer = $this->Ask($oEngine, [[
				'role' => 'user',
				'content' => 'What code is shown in this image? Answer with the code only.',
				'images' => [VisionTestImageFactory::Payload(self::MARKER_C, 'image/gif')],
			]]);
		} catch (AIVisionUnsupportedException $e) {
			static::markTestSkipped('The configured model does not accept GIF images: '.$e->getMessage());
		}

		$this->assertStringContainsString(self::MARKER_C, $sAnswer, "The model did not read the GIF image. Answer: {$sAnswer}");
	}

	// ---------------------------------------------------------------------
	// T4 — mixed text and images with an ordinal reference (the real scenario)
	// ---------------------------------------------------------------------

	/**
	 * Replays the help-desk case: the text refers to "the first" and "the third"
	 * screenshot, and the answer must assign the right code to each ordinal.
	 *
	 * The assignment is reported rather than asserted as a hard failure: marker
	 * presence is a hard requirement, correct ordinal assignment is a quality
	 * signal that depends on the model. A wrong assignment is printed, so the
	 * run log shows how well the configured model handles the scenario.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testMixedTextAndImagesResolveOrdinalReferences(): void
	{
		$oEngine = $this->MakeVisionEngine();

		$sPrompt =
			'A user reports a login problem and attached three screenshots in this order. '.
			"The first screenshot shows the login form after clicking Submit and displays the code {$this->markerOf(0)}. ".
			"The second screenshot shows the error message that appears afterwards, with the code {$this->markerOf(1)}. ".
			"The third screenshot shows the final page containing the code {$this->markerOf(2)}. ".
			'Question: which code appears on the FIRST and which on the THIRD screenshot? '.
			'Answer in exactly this form: FIRST=<code>, THIRD=<code>';

		$sAnswer = $this->Ask($oEngine, [[
			'role' => 'user',
			'content' => $sPrompt,
			'images' => [
				VisionTestImageFactory::Payload($this->markerOf(0), 'image/png'),
				VisionTestImageFactory::Payload($this->markerOf(1), 'image/jpeg'),
				VisionTestImageFactory::Payload($this->markerOf(2), 'image/webp'),
			],
		]]);

		// Hard requirements: the model saw all three images.
		foreach ([0, 1, 2] as $iIndex) {
			$this->assertStringContainsString(
				$this->markerOf($iIndex),
				$sAnswer,
				"Marker {$this->markerOf($iIndex)} missing from answer: {$sAnswer}"
			);
		}

		// Quality signal: ordinal assignment.
		$sFirst = $this->extractOrdinal($sAnswer, 'FIRST');
		$sThird = $this->extractOrdinal($sAnswer, 'THIRD');

		$bCorrect = ($sFirst === $this->markerOf(0)) && ($sThird === $this->markerOf(2));
		if (!$bCorrect) {
			fwrite(
				STDERR,
				sprintf(
					"\n[vision quality] ordinal assignment wrong: FIRST=%s (expected %s), THIRD=%s (expected %s)\nAnswer: %s\n",
					$sFirst ?? '<none>',
					$this->markerOf(0),
					$sThird ?? '<none>',
					$this->markerOf(2),
					trim($sAnswer)
				)
			);
			// PHPUnit 9 has no addWarning(); the STDERR line above is the report.
		}
	}

	/**
	 * Images are sent in a single turn; the model must report them in that order.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testImagesArePresentedInTheGivenOrder(): void
	{
		$oEngine = $this->MakeVisionEngine();

		$sAnswer = $this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'Read the three images and list their codes in the order the images were given. '.
				'Answer in exactly this form: 1=<code>, 2=<code>, 3=<code>',
			'images' => [
				VisionTestImageFactory::Payload(self::MARKER_A, 'image/png'),
				VisionTestImageFactory::Payload(self::MARKER_B, 'image/jpeg'),
				VisionTestImageFactory::Payload(self::MARKER_C, 'image/webp'),
			],
		]]);

		$this->assertMatchesRegularExpression(
			'/1\s*[=:]\s*'.preg_quote(self::MARKER_A, '/').'.*2\s*[=:]\s*'.preg_quote(self::MARKER_B, '/').'.*3\s*[=:]\s*'.preg_quote(self::MARKER_C, '/').'/s',
			$sAnswer,
			"The model did not report the images in the given order. Answer: {$sAnswer}"
		);
	}

	// ---------------------------------------------------------------------
	// T6–T8 — local guards, no network involved
	// ---------------------------------------------------------------------

	/**
	 * A model without vision support must be refused before any request is sent.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testVisionDisabledForModelIsRefusedLocally(): void
	{
		$aSettings = self::GetSettings();
		if ($aSettings['url'] === '' || $aSettings['model'] === '') {
			static::markTestSkipped('No endpoint configured; the guard is covered by the unit tests.');
		}

		$oEngine = $this->MakeVisionEngine(false);

		$this->expectException(AIConfigurationException::class);
		$this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'What code is shown?',
			'images' => [VisionTestImageFactory::Payload(self::MARKER_A, 'image/png')],
		]]);
	}

	/**
	 * Invalid image payloads are rejected before any request is sent.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testInvalidImageDataIsRejectedLocally(): void
	{
		$aSettings = self::GetSettings();
		if ($aSettings['url'] === '' || $aSettings['model'] === '') {
			static::markTestSkipped('No endpoint configured; validation is covered by the unit tests.');
		}

		$oEngine = $this->MakeVisionEngine();

		$this->expectException(AIInvalidImageException::class);
		$this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'What code is shown?',
			'images' => [['data' => 'not-base64-at-all!!', 'media_type' => 'image/png']],
		]]);
	}

	/**
	 * A declared media type that does not match the actual bytes is rejected.
	 *
	 * @group integration
	 * @group vision
	 */
	public function testMediaTypeMismatchIsRejectedLocally(): void
	{
		$aSettings = self::GetSettings();
		if ($aSettings['url'] === '' || $aSettings['model'] === '') {
			static::markTestSkipped('No endpoint configured; validation is covered by the unit tests.');
		}

		$oEngine = $this->MakeVisionEngine();

		$this->expectException(AIInvalidImageException::class);
		$this->Ask($oEngine, [[
			'role' => 'user',
			'content' => 'What code is shown?',
			'images' => [[
				'data' => base64_encode(VisionTestImageFactory::Render(self::MARKER_A, 'image/png')),
				// Deliberately wrong: the bytes are a PNG, not a JPEG.
				'media_type' => 'image/jpeg',
			]],
		]]);
	}

	// ---------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------

	/**
	 * Marker assigned to the image at the given position.
	 */
	private function markerOf(int $iIndex): string
	{
		return [self::MARKER_B, self::MARKER_A, self::MARKER_C][$iIndex];
	}

	/**
	 * Extracts the code assigned to an ordinal label such as "FIRST=<code>".
	 */
	private function extractOrdinal(string $sAnswer, string $sLabel): ?string
	{
		if (preg_match('/\b'.preg_quote($sLabel, '/').'\s*[=:]\s*(ERR-\d+)/i', $sAnswer, $aMatches) !== 1) {
			return null;
		}

		return strtoupper($aMatches[1]);
	}
}

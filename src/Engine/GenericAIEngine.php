<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 * @author David Gümbel <david.guembel@itomig.de>
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with iTop. If not, see <http://www.gnu.org/licenses/>
 */

namespace Itomig\iTop\Extension\AIBase\Engine;

use IssueLog;
use Itomig\iTop\Extension\AIBase\Exception\AIAuthException;
use Itomig\iTop\Extension\AIBase\Exception\AIContextWindowException;
use Itomig\iTop\Extension\AIBase\Exception\AIEngineException;
use Itomig\iTop\Extension\AIBase\Exception\AIInvalidImageException;
use Itomig\iTop\Extension\AIBase\Exception\AINetworkException;
use Itomig\iTop\Extension\AIBase\Exception\AIRateLimitException;
use Itomig\iTop\Extension\AIBase\Exception\AIVisionUnsupportedException;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\Message;
use LLPhant\Chat\Anthropic\AnthropicVisionMessage;
use LLPhant\Chat\Vision\VisionMessage;
use LLPhant\Exception\HttpException;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;

abstract class GenericAIEngine implements iAIEngineInterface
{
	/**
	 * @var string $url
	 */
	protected $url;

	/**
	 * @var string $apiKey
	 */
	protected $apiKey;

	/**
	 * @var string $model
	 */
	protected $model;

	protected bool $supportsVision = false;

	protected const MAX_VISION_IMAGE_BYTES = 5 * 1024 * 1024;

	public function __construct(string $url, string $apiKey, string $model, bool $supportsVision = false)
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
		$this->supportsVision = $supportsVision;
	}

	public function SupportsVision(): bool
	{
		return $this->supportsVision;
	}

	protected static function GetConfiguredVisionSupport(array $configuration): bool
	{
		$value = $configuration['supports_vision'] ?? false;

		return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

	/**
	 * Validates and normalizes the shared base64 image payload contract.
	 *
	 * @param array<int, mixed> $aImages
	 * @return array<int, array{data: string, media_type: string}>
	 * @throws AIInvalidImageException
	 */
	protected function NormalizeVisionImages(array $aImages): array
	{
		if ($aImages === []) {
			throw new AIInvalidImageException('At least one valid image is required for image input.');
		}

		$aNormalizedImages = [];
		$iMaxBase64Length = 4 * intdiv(self::MAX_VISION_IMAGE_BYTES + 2, 3);
		foreach ($aImages as $iIndex => $aImage) {
			if (!is_array($aImage)) {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: expected an image object.");
			}

			$sData = preg_replace('/\s+/', '', trim((string) ($aImage['data'] ?? '')));
			$sMediaType = strtolower(trim((string) ($aImage['media_type'] ?? '')));

			if ($sData === '' || $sMediaType === '') {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: data and media_type are required.");
			}

			if (!in_array($sMediaType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: unsupported media type '{$sMediaType}'.");
			}

			if (preg_match('/^[a-zA-Z0-9+\/]*={0,2}$/', $sData) !== 1) {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: data is not valid base64.");
			}

			if (strlen($sData) > $iMaxBase64Length) {
				throw new AIInvalidImageException(
					"Invalid image at index {$iIndex}: encoded data exceeds the maximum image size."
				);
			}

			$sBinaryData = base64_decode($sData, true);
			if ($sBinaryData === false) {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: data is not valid base64.");
			}

			$iImageBytes = strlen($sBinaryData);
			if ($iImageBytes > self::MAX_VISION_IMAGE_BYTES) {
				throw new AIInvalidImageException(
					"Invalid image at index {$iIndex}: decoded data exceeds the maximum image size."
				);
			}

			$sDetectedMediaType = $this->DetectImageMediaType($sBinaryData);
			if ($sDetectedMediaType === null || $sDetectedMediaType !== $sMediaType) {
				throw new AIInvalidImageException("Invalid image at index {$iIndex}: media_type does not match the image data.");
			}

			$aNormalizedImages[] = [
				'data' => $sData,
				'media_type' => $sMediaType,
			];
		}

		return $aNormalizedImages;
	}

	private function DetectImageMediaType(string $sBinaryData): ?string
	{
		if (str_starts_with($sBinaryData, "\x89PNG\x0D\x0A\x1A\x0A")) {
			return 'image/png';
		}

		$sGifHeader = substr($sBinaryData, 0, 6);
		if ($sGifHeader === 'GIF87a' || $sGifHeader === 'GIF89a') {
			return 'image/gif';
		}

		if (str_starts_with($sBinaryData, "\xFF\xD8")) {
			return 'image/jpeg';
		}

		if (str_starts_with($sBinaryData, 'RIFF') && substr($sBinaryData, 8, 4) === 'WEBP') {
			return 'image/webp';
		}

		return null;
	}

	/**
	 * Abstract method that concrete engine classes must implement to provide
	 * their specific llphant chat instance.
	 *
	 * @return ChatInterface
	 */
	abstract protected function createChatInstance(): ChatInterface;

	/**
	 * Maps an LLPhant HttpException to a typed AI engine exception
	 * based on the HTTP status code and message content.
	 *
	 * @param HttpException $e
	 * @param bool $bVisionRequest Whether the failed request contained image input
	 * @return AIEngineException
	 */
	protected function classifyHttpException(HttpException $e, bool $bVisionRequest = false): AIEngineException
	{
		$iCode = $e->getCode();
		$sMsg  = $e->getMessage();

		if ($iCode === 429) {
			return new AIRateLimitException($sMsg, $iCode, $e);
		}
		if ($iCode === 401 || $iCode === 403) {
			return new AIAuthException($sMsg, $iCode, $e);
		}
		if ($iCode === 413) {
			return new AIContextWindowException($sMsg, $iCode, $e);
		}
		// HTTP 400 with token/context length keywords indicates context overflow
		if ($iCode === 400) {
			$sMsgLower = strtolower($sMsg);
			if (str_contains($sMsgLower, 'context') || str_contains($sMsgLower, 'token') ||
				str_contains($sMsgLower, 'maximum') || str_contains($sMsgLower, 'too long') ||
				str_contains($sMsgLower, 'length')) {
				return new AIContextWindowException($sMsg, $iCode, $e);
			}
		}
		if ($bVisionRequest && $this->isVisionUnsupportedMessage($sMsg)) {
			return new AIVisionUnsupportedException(
				'The configured AI model or endpoint does not support image input. Select a vision-capable model. Provider response: '.$sMsg,
				$iCode,
				$e
			);
		}

		return new AINetworkException($sMsg, $iCode, $e);
	}

	private function isVisionUnsupportedMessage(string $sMessage): bool
	{
		$sMessageLower = strtolower($sMessage);
		$aUnsupportedVisionPatterns = [
			'/\bno\s+(?:(?:available|compatible|matching)\s+)?endpoints?\b.{0,100}\b(?:image(?:s)?(?:[\s_-]+url|\s+inputs?)|visual(?:\s+inputs?)?|vision(?:\s+inputs?)?|multimodal(?:\s+inputs?)?)\b/',
			'/\b(?:image(?:s)?(?:[\s_-]+url|\s+inputs?)|visual|vision|multimodal)(?:\s+\w+){0,5}\s+only\s+(?:supported|available|allowed|accepted)\s+(?:by|for|with)\b/',
			'/\b(?:does\s+not|doesn\'t|cannot|can\'t|will\s+not|won\'t)\s+(?:support|accept|process|handle|allow|permit|have)\s+(?:the\s+)?(?:image(?:s)?(?:[\s_-]+url)?|visual|vision|multimodal)(?:\s+(?:inputs?|content(?:\s+blocks?)?|parts?|data|capabilit(?:y|ies)))?\b/',
			'/\b(?:image(?:s)?(?:[\s_-]+url)?|visual|vision|multimodal)(?:\s+(?:inputs?|content(?:\s+blocks?)?|parts?|data|modality|capabilit(?:y|ies)|models?)){0,2}\s+(?:is|are)?\s*(?:not\s+supported|unsupported|not\s+available|unavailable|not\s+allowed|not\s+accepted|not\s+permitted|not\s+compatible(?:\s+with)?|disabled)\b/',
			'/\b(?:model|endpoint|provider|engine|deployment)\b.{0,80}\b(?:not\s+multimodal|not\s+vision(?:[-\s]capable)?|not\s+(?:a\s+)?(?:vision|multimodal)\s+model|not\s+capable\s+of\s+(?:processing\s+)?(?:images?|visual\s+input|vision)|only\s+(?:supports?|accepts?|handles?)\s+text|(?:supports?|accepts?|handles?)\s+text\s+only)\b/',
			'/\bunsupported\s+(?:(?:(?:input|content)\s+type\s*:?\s*)(?:image(?:s)?(?:[\s_-]+url)?|visual|vision|multimodal)|(?:image(?:s)?(?:[\s_-]+url)?|visual|vision|multimodal)\s+inputs?)\b/',
		];

		foreach ($aUnsupportedVisionPatterns as $sPattern) {
			if (preg_match($sPattern, $sMessageLower) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generic implementation for handling a conversational turn.
	 * This method uses the Template Method Pattern, relying on createChatInstance from subclasses.
	 *
	 * The engine does NOT execute tools - it returns FunctionInfo[] to the caller (AIService)
	 * which handles tool execution and the multi-step loop.
	 *
	 * @param Message[] $aHistory The conversation history.
	 * @param FunctionInfo[] $aTools Optional array of tools for function calling.
	 * @return string|FunctionInfo[] String for text response, FunctionInfo[] for tool calls
	 */
	public function GetNextTurn(array $aHistory, array $aTools = []): string|array
	{
		$oChat = $this->createChatInstance();
		$sSystemMessage = '';
		$aMessageHistory = [];

		// Register tools on the chat instance if provided
		if (!empty($aTools)) {
			foreach ($aTools as $oTool) {
				$oChat->addTool($oTool);
			}
			IssueLog::Debug(__METHOD__ . ": Registered " . count($aTools) . " tools for function calling.", AIBaseHelper::MODULE_CODE);
			$aToolNames = array_map(fn($t) => $t->name, $aTools);
			IssueLog::Debug(__METHOD__ . ": Tool names: " . implode(', ', $aToolNames), AIBaseHelper::MODULE_CODE);
		}

		// Extract the FIRST (and only) System-Message if present
		// (System-Message was set by ContinueConversation as first message)
		if (!empty($aHistory) && $aHistory[0]->role === ChatRole::System) {
			$sSystemMessage = $aHistory[0]->content;
			$aHistory = array_slice($aHistory, 1); // Remove for generateChat()
		}

		// Defense in Depth: Verify no additional system messages leaked through
		foreach ($aHistory as $oMessage) {
			if ($oMessage->role === ChatRole::System) {
				// This should NEVER happen - system messages should have been filtered in ContinueConversation
				IssueLog::Error(__METHOD__ . ": System message leaked into history after filtering!", AIBaseHelper::MODULE_CODE);
				continue; // Skip - do not add
			}
			$aMessageHistory[] = $oMessage;
		}

		if (!empty($sSystemMessage)) {
			$oChat->setSystemMessage($sSystemMessage);
		}

		// Debug: Log message details including tool call information
		foreach ($aMessageHistory as $idx => $oMsg) {
			$aDetails = ['role' => $oMsg->role->value, 'content_length' => strlen($oMsg->content ?? '')];
			if (!empty($oMsg->tool_calls)) {
				$aDetails['tool_calls_count'] = count($oMsg->tool_calls);
			}
			if (!empty($oMsg->tool_call_id)) {
				$aDetails['tool_call_id'] = $oMsg->tool_call_id;
			}
			IssueLog::Debug(__METHOD__ . ": Message[$idx]: " . json_encode($aDetails), AIBaseHelper::MODULE_CODE);
		}

		IssueLog::Debug(__METHOD__ . ": Calling AI Engine with a conversation history of " . count($aMessageHistory) . " turns.", AIBaseHelper::MODULE_CODE);
		$bVisionRequest = false;
		foreach ($aMessageHistory as $oMessage) {
			if ($oMessage instanceof VisionMessage || $oMessage instanceof AnthropicVisionMessage) {
				$bVisionRequest = true;
				break;
			}
		}
		try {
			$result = $oChat->generateChatOrReturnFunctionToCall($aMessageHistory);
		} catch (\LLPhant\Exception\HttpException $e) {
			throw $this->classifyHttpException($e, $bVisionRequest);
		} catch (\GuzzleHttp\Exception\ConnectException $e) {
			throw new AINetworkException('AI engine unreachable: ' . $e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			throw new AINetworkException('Unexpected AI engine error: ' . $e->getMessage(), 0, $e);
		}

		if (is_string($result)) {
			$sResponsePreview = strlen($result) > 500 ? substr($result, 0, 500) . '...[truncated]' : $result;
			IssueLog::Debug(__METHOD__ . ": Text response: " . $sResponsePreview, AIBaseHelper::MODULE_CODE);
			return $result;
		}

		// Tool calls requested by LLM - return FunctionInfo[] to caller (AIService)
		$aToolNames = array_map(fn($t) => $t->name, $result);
		IssueLog::Debug(__METHOD__ . ": LLM requested tool calls: " . implode(', ', $aToolNames), AIBaseHelper::MODULE_CODE);
		return $result;
	}
}


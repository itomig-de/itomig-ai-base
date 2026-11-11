<?php
/*
 * @copyright Copyright (C) 2024,2025 ITOMIG GmbH
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

use GuzzleHttp\Exception\ConnectException;
use Itomig\iTop\Extension\AIBase\Exception\AINetworkException;
use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;
use LLPhant\Chat\ChatInterface;

class AnthropicAIEngine extends GenericAIEngine implements iAIEngineInterface
{
	/**
	 * @inheritDoc
	 */
	public static function GetEngineName(): string
	{
		return 'AnthropicAI';
	}

	/**
	 * Base URL that LLPhant appends "/v1/messages" to.
	 */
	private const DEFAULT_BASE_URL = 'https://api.anthropic.com';

	/**
	 * LLPhant appends "/v1/messages" to whatever base URL it is given, so a
	 * configuration that still carries the full endpoint path has to be reduced
	 * to its base. Configurations written before the LLPhant 1.0.10 upgrade use
	 * 'https://api.anthropic.com/v1/messages'.
	 *
	 * @inheritDoc
	 */
	public static function GetEngine(array $configuration): AnthropicAIEngine
	{
		$url = self::ToBaseUrl($configuration['url'] ?? self::DEFAULT_BASE_URL);
		$model = $configuration['model'] ?? 'claude-3-5-sonnet-latest';
		$apiKey = $configuration['api_key'] ?? '';
		return new self($url, $apiKey, $model);
	}

	/**
	 * Strips a trailing "/v1/messages" (and any trailing slash) from a configured
	 * endpoint so that it can be handed to LLPhant as a base URL.
	 *
	 * @param string $sUrl configured URL, with or without the Messages API path
	 * @return string the base URL without the Messages API path
	 */
	private static function ToBaseUrl(string $sUrl): string
	{
		$sUrl = rtrim($sUrl, '/');
		if (str_ends_with($sUrl, '/v1/messages')) {
			$sUrl = substr($sUrl, 0, -strlen('/v1/messages'));
		}
		return $sUrl;
	}

	/**
	 * Ask Anthropic AI a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 */
	public function GetCompletion(string $message, string $systemInstruction = '') : string
	{
		$oChat = $this->createChatInstance();
		$oChat->setSystemMessage($systemInstruction);
		try {
			$response = $oChat->generateText($message);
		} catch (\LLPhant\Exception\HttpException $e) {
			throw $this->classifyHttpException($e);
		} catch (ConnectException $e) {
			throw new AINetworkException('AI engine unreachable: ' . $e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			throw new AINetworkException('Unexpected AI engine error: ' . $e->getMessage(), 0, $e);
		}
		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);
		return $response;
	}

	/**
	 * Creates and returns an instance of AnthropicChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		// TODO: maxTokens (4096) should be configurable (see Post-Implementation notes in plan)
		$oConfig = new AnthropicConfig(
			$this->model,
			4096,
			array(),
			$this->apiKey,
			url: $this->url === '' ? null : $this->url
		);
		$oChat = new AnthropicChat($oConfig);
		return $oChat;
	}
}

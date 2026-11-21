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
use Itomig\iTop\Extension\AIBase\Exception\AIConfigurationException;
use Itomig\iTop\Extension\AIBase\Exception\AINetworkException;
use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;
use LLPhant\Chat\ChatInterface;

class AnthropicAIEngine extends GenericAIEngine implements iAIEngineInterface
{
	/**
	 * The max_tokens value of the Anthropic request. Configurable through the
	 * 'max_tokens' entry of ai_engine.configuration (#86); its own request
	 * field takes precedence over model_options entries of the same name.
	 *
	 * @var int $iMaxTokens
	 */
	private $iMaxTokens;

	/**
	 * @inheritDoc
	 */
	public function __construct(string $url, string $apiKey, string $model, array $aModelOptions = [], int $iMaxTokens = 4096)
	{
		$this->iMaxTokens = $iMaxTokens;
		parent::__construct($url, $apiKey, $model, $aModelOptions);
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngineName(): string
	{
		return 'AnthropicAI';
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine(array $configuration): AnthropicAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.anthropic.com/v1/messages';
		$model = $configuration['model'] ?? 'claude-3-5-sonnet-latest';
		$apiKey = $configuration['api_key'] ?? '';
		$aModelOptions = self::ReadModelOptions($configuration);

		// The Anthropic API takes max_tokens as a first-class request field with
		// no provider default we could rely on, so it is a dedicated setting
		// rather than a model_options entry (which the request builder would
		// override with this value anyway).
		$iMaxTokens = $configuration['max_tokens'] ?? 4096;
		if (!is_int($iMaxTokens) && !ctype_digit((string) $iMaxTokens)) {
			throw new AIConfigurationException(
				'ai_engine.configuration: max_tokens must be a positive integer, got: '.var_export($iMaxTokens, true)
			);
		}

		return new self($url, $apiKey, $model, $aModelOptions, (int) $iMaxTokens);
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
		// maxTokens is configurable through the 'max_tokens' setting (#86).
		$oConfig = new AnthropicConfig($this->model, $this->iMaxTokens, $this->aModelOptions, $this->apiKey);
		$oChat = new AnthropicChat($oConfig);
		return $oChat;
	}
}

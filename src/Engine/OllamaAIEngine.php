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
use LLPhant\Chat\ChatInterface;
use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChat;

class OllamaAIEngine extends GenericAIEngine implements iAIEngineInterface
{
	/**
	 * @inheritDoc
	 */
	public static function GetEngineName(): string
	{
		return 'OllamaAI';
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine(array $configuration): OllamaAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $configuration['model'] ?? 'gpt-3.5-turbo';
		$apiKey = $configuration['api_key'] ?? '';
		$aModelOptions = self::ReadModelOptions($configuration);

		return new self($url, $apiKey, $model, $aModelOptions);
	}

	/**
	 * Ask Ollama a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 */
	public function GetCompletion(string $message, string $systemInstruction = '') : string
	{
		$oChat = $this->createChatInstance();
		\IssueLog::Debug("OllamaAIEngine: about to set system instruction: ".$systemInstruction);
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
	 * Creates and returns an instance of OllamaChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		$oConfig = new OllamaConfig();
		//$oConfig->apiKey = $this->apiKey;  // Ollama doesn't need API key
		$oConfig->url = $this->url;
		$oConfig->model = $this->model;
		// Passed through verbatim onto the /api/chat payload (#86/#41).
		// Note the Ollama API shape: numeric model parameters (num_ctx,
		// temperature, ...) belong under the 'options' key, so they must be
		// configured nested: model_options = array('options' => array('num_ctx' => 16384, ...)),
		// while top-level request fields (template, keep_alive, ...) are direct
		// model_options entries.
		$oConfig->modelOptions = $this->aModelOptions;

		$oChat = new OllamaChat($oConfig);
		return $oChat;
	}
}

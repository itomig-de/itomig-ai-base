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
	public static function GetEngine($configuration): OllamaAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $configuration['model'] ?? 'gpt-3.5-turbo';
		$apiKey = $configuration['api_key'] ?? '';

		return new self($url, $apiKey, $model);
	}

	/**
	 * Ask Ollama a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 */
	public function GetCompletion($message, $systemInstruction = '') : string
	{
		$oChat = $this->createChatInstance();
		\IssueLog::Debug("OllamaAIEngine: about to set system instruction: ".$systemInstruction);
		$oChat->setSystemMessage($systemInstruction);
		$response = $oChat->generateText($message);
		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);

		// TODO error handling in LLPhant ( #2) ?
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

		$oChat = new OllamaChat($oConfig);
		return $oChat;
	}
}

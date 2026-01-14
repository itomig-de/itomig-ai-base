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
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use LLPhant\Chat\ChatInterface;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;

class OpenAIEngine extends GenericAIEngine implements iAIEngineInterface
{

	/**
	 * @inheritDoc
	 */
	public static function GetEngineName(): string
	{
		return 'OpenAI';
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): OpenAIEngine
	{
		$url = $configuration['url'] ?? '';
		$model = $configuration['model'] ?? 'gpt-4o-mini';
		$apiKey = $configuration['api_key'] ?? '';
		return new self($url, $apiKey, $model);
	}


	/**
	 * Ask OpenAI a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @param int $retryNumber Number of attempts (default: 3, must be >= 1)
	 * @return string the textual response
	 */
	public function GetCompletion($message, $systemInstruction = '', int $retryNumber = 3) : string
	{
		IssueLog::Debug("OpenAIEngine: getCompletions() called");
		$oChat = $this->createChatInstance();
		$oChat->setSystemMessage($systemInstruction);

		IssueLog::Debug("OpenAIEngine: system Message set, next step: generateText()..");

		// Execute with retry logic and exponential backoff
		return AIBaseHelper::executeWithRetry(
			fn() => $oChat->generateText($message),
			$retryNumber,
			'OpenAIEngine'
		);
	}

	/**
	 * Creates and returns an instance of OpenAIChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		$oConfig = new OpenAIConfig();
		$oConfig->apiKey = $this->apiKey;
		if (!empty($this->url)) {
			$oConfig->url = $this->url;
		}
		if (!empty($this->model)) {
			$oConfig->model = $this->model;
		}
		$oChat = new OpenAIChat($oConfig);
		return $oChat;
	}
}

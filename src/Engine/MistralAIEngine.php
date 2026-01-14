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

use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use LLPhant\Chat\ChatInterface;
use LLPhant\MistralAIConfig;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\MistralAIChat;

class MistralAIEngine extends GenericAIEngine implements iAIEngineInterface
{

	/**
	 * @inheritDoc
	 */
	public static function GetEngineName(): string
	{
		return 'MistralAI';
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): MistralAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.mistral.ai/v1/chat/completions';
		$model = $configuration['model'] ?? 'mistral-large-latest';
		$apiKey = $configuration['api_key'] ?? '';

        return new self($url, $apiKey, $model);
	}

	/**
	 * Ask Mistral AI a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @param int $retryNumber Number of attempts (default: 3, must be >= 1)
	 * @return string the textual response
	 */
	public function GetCompletion($message, $systemInstruction = '', int $retryNumber = 3) : string
	{
		$oChat = $this->createChatInstance();
		$oChat->setSystemMessage($systemInstruction);

		// Execute with retry logic and exponential backoff
		return AIBaseHelper::executeWithRetry(
			fn() => $oChat->generateText($message),
			$retryNumber,
			'MistralAIEngine'
		);
	}

	/**
	 * Creates and returns an instance of MistralAIChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		$oConfig = new MistralAIConfig($this->apiKey, $this->url, $this->model);
		$oChat = new MistralAIChat($oConfig);
		return $oChat;
	}
}

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
     * @inheritDoc
     */
	public static function GetEngine($configuration): AnthropicAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.anthropic.com/v1/messages';
		$model = $configuration['model'] ?? 'claude-3-5-sonnet-latest';
		$apiKey = $configuration['api_key'] ?? '';
        return new self($url, $apiKey, $model);
	}

	/**
	 * Ask Anthropic AI a question, retrieve the answer and return it in text form
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
			'AnthropicAIEngine'
		);
	}

	/**
	 * Creates and returns an instance of AnthropicChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		// TODO: maxTokens (4096) should be configurable (see Post-Implementation notes in plan)
		$oConfig = new AnthropicConfig($this->model, 4096, array(), $this->apiKey);
		$oChat = new AnthropicChat($oConfig);
		return $oChat;
	}
}

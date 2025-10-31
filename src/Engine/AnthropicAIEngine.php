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

use Dict;
use Exception;
use IssueLog;
use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;

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
     * @param int $retryNumber Number of retries in case of failure. Must be at least 1.
    * @return string the textual response
	 */
	public function GetCompletion($message, $systemInstruction = '', int $retryNumber = 3): string
	{

		$config = new AnthropicConfig($this->model, 4096, array() , $this->apiKey);
		$chat = new AnthropicChat($config);

		$chat->setSystemMessage ($systemInstruction);
		IssueLog::Debug('AnthropicAIEngine: system Message set, next step: generateText()..');
		for ($i = 0; $i < $retryNumber; $i++) {
			try {
				$response = $chat->generateText($message);
				IssueLog::Debug(__METHOD__);
				IssueLog::Debug($response);

				return $response;
			}
			catch (Exception $e) {
				IssueLog::Error('AnthropicAIEngine: Error during generateText() attempt '.($i + 1).'/'.$retryNumber.': '.$e->getMessage());
			}
		}
		throw new Exception(Dict::S('itomig-ai-base/ErrorAIEngineConnexion'));
	}
}

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

use LLPhant\AnthropicConfig;
use LLPhant\Chat\AnthropicChat;
use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;

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
	public static function GetPrompts(): array
	{
		$aGenericPrompts = GenericAIEngine::GetPrompts();
		// TODO add more prompts once they are implemented :)
		return $aGenericPrompts;
	}

    /**
     * @inheritDoc
     */
	public static function GetEngine($configuration): AnthropicAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.anthropic.com/v1/messages';
		$model = $configuration['model'] ?? 'claude-3-5-sonnet-latest';
		$aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
		$apiKey = $configuration['api_key'] ?? '';
		$aSystemPrompts = $configuration['system_prompts'] ?? null;
        
        if (empty($aSystemPrompts)) {
            return new self($url, $apiKey, $model, $aLanguages);
        }
        
        return new self($url, $apiKey, $model, $aLanguages, $aSystemPrompts);
	}

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

    /**
     * @var string[] $languages
     */
    protected $languages;

	/**
	 * @var array $aSystemPrompts
	 */
	public $aSystemPrompts;


	/**
	 * Ask Anthropic AI a question, retrieve the answer and return it in text form
	 *
	 * @param string $sMessage
	 * @param string $sSystemPrompt optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	public function getCompletions($sMessage, $sSystemPrompt = "You are a helpful assistant. You answer inquiries politely, precisely, and briefly. ") {

		$config = new AnthropicConfig($this->model, 4096, array() , $this->apiKey);
		$chat = new AnthropicChat($config);

		$chat->setSystemMessage ($sSystemPrompt);
		$response = $chat->generateText($sMessage);

		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);

		// TODO error handling in LLPhant: Catch LLPhantException ( #2) ?
		return $response;
	}
}
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

use LLPhant\OpenAIConfig;
use LLPhant\Chat\MistralAIChat;
use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;

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
	public static function GetPrompts(): array
	{
		$aGenericPrompts = GenericAIEngine::GetPrompts();
		// TODO add more prompts once they are implemented :)
		return $aGenericPrompts;
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): MistralAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.mistral.ai/v1/chat/completions';
		$model = $configuration['model'] ?? 'mistral-large-latest';
		$aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
		$apiKey = $configuration['api_key'] ?? '';
		$aSystemPrompts = $configuration['system_prompts'] ?? null;
		if (empty($aSystemPrompts)) {
            return new self($url, $apiKey, $model, $aLanguages);
        }
        
        return new self($url, $apiKey, $model, $aLanguages, $aSystemPrompts );
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
	 * @inheritDoc
	 * @throws AIResponseException
	 */
	public function PerformPrompt($prompt, $text, $object): string
	{
		switch ($prompt)
		{
			case 'translate':
				return $this->translate($text);

			case 'improveText':
				return $this->improveText($text); 

			default:
			return parent::PerformPrompt($prompt, $text, $object);
		}
	}

	/**
	 * Ask Mistral AI a question, retrieve the answer and return it in text form
	 *
	 * @param string $sMessage
	 * @param string $sSystemPrompt optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	public function getCompletions($sMessage, $sSystemPrompt = "You are a helpful assistant. You answer inquiries politely, precisely, and briefly. ") {

		$config = new OpenAIConfig();
		$config->apiKey = $this->apiKey;
		$config->url = $this->url;
		$config->model=$this->model;
		$chat = new MistralAIChat($config);

		$chat->setSystemMessage ($sSystemPrompt);
		$response = $chat->generateText($sMessage);

		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);

		// TODO error handling in LLPhant (#2 )
		return $response;
	}

}

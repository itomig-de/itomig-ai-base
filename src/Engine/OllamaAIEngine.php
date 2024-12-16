<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
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

use LLPhant\OllamaConfig;
use LLPhant\Chat\OllamaChat;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;

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
	public static function GetPrompts(): array
	{
		$aGenericPrompts = GenericAIEngine::GetPrompts();
		$aAdditionalPrompts = array(
			[
				'label' => 'UI:AIResponse:GenericAI:Prompt:rephraseTicket',
				'prompt' => 'rephraseTicket',
			],
			[
				'label' => 'UI:AIResponse:GenericAI:Prompt:summarizeChildren',
				'prompt' => 'summarizeChildren',
			]
			);
		array_push($aGenericPrompts,$aAdditionalPrompts[0],$aAdditionalPrompts[1]);

		// TODO add more prompts once they are implemented :)
		return $aGenericPrompts;
	}


	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): OllamaAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $configuration['model'] ?? 'gpt-3.5-turbo';
		$aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
		$aSystemPrompts = $configuration['system_prompts'] ?? [];
		$apiKey = $configuration['api_key'] ?? [];
		return new self($url, $apiKey, $model, $aLanguages, $aSystemPrompts);
	}


	/**
	 * @inheritDoc
	 * @throws AIResponseException
	 */
	public function PerformPrompt($prompt, $text, $object): string
	{
		switch ($prompt)
		{
			case 'rephraseTicket':
				return $this->rephraseTicket($object);
			case 'summarizeChildren':
				return $this->summarizeChildren($object);
			default:
			    return parent::PerformPrompt($prompt, $text, $object);
		}
	}


		/**
	 * Ask OpenAI a question, retrieve the answer and return it in text form
	 *
	 * @param string $sMessage
	 * @param string $sSystemPrompt optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	protected function getCompletions($sMessage, $sSystemPrompt = "You are a helpful assistant. You answer inquiries politely, precisely, and briefly. ") {

		$config = new OllamaConfig();
		//$config->apiKey = $this->apiKey;
		$config->url = $this->url;
		$config->model = $this->model;
		$chat = new OllamaChat($config);

		$chat->setSystemMessage ($sSystemPrompt);
		$response = $chat->generateText($sMessage);
		\IssueLog::Info(__METHOD__);
		\IssueLog::Info($response);

		// TODO error handling in LLPhant - ?
		// TODO num_ctx parameter...?
		return $response;
	}

}

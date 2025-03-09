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

use LLPhant\OpenAIConfig;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;
use LLPhant\Chat\OpenAIChat;

use Combodo\iTop\IssueLog;

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
	public static function GetEngine($configuration): OpenAIEngine
	{
		$url = $configuration['url'] ?? '';
		$model = $configuration['model'] ?? '';
		$aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
	    $aSystemPrompts = $configuration['system_prompts'] ?? null;
		$apiKey = $configuration['api_key'] ?? [];
		if (empty($aSystemPrompts)) {
            return new self($url, $apiKey, $model, $aLanguages);
        }

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
	public function getCompletions($sMessage, $sSystemPrompt = "You are a helpful assistant. You answer inquiries politely, precisely, and briefly. ") {

		\IssueLog::Debug("OpenAIEngine: getCompletions() called");
		$config = new OpenAIConfig();
		$config->apiKey = $this->apiKey;
		if(!empty($this->url)) {
			$config->url = $this->url;
		}
		if(!empty($this->model)) {
			$config->model = $this->model;
		}
		$chat = new OpenAIChat($config);
        $chat->setSystemMessage ($sSystemPrompt);

		\IssueLog::Debug("OpenAIEngine: system Message set, next step: generateText()..");
		$response = $chat->generateText($sMessage);
		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);
		return $response;

		// TODO error handling in LLPhant: Catch LLPhant\Exception ?
	}



}

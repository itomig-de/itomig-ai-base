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
	public static function GetEngine($configuration): OllamaAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $configuration['model'] ?? 'gpt-3.5-turbo';
		$aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
		$apiKey = $configuration['api_key'] ?? '';
		$aSystemPrompts = $configuration['system_prompts'] ?? [];

		return new self($url, $apiKey, $model, $aLanguages, $aSystemPrompts);
	}

	/**
	 * Ask Ollama a question, retrieve the answer and return it in text form
	 *
	 * @param string $sMessage
	 * @param string $sSystemPrompt optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	public function getCompletions($sMessage, $sSystemPrompt = "You are a helpful assistant. You answer inquiries politely, precisely, and briefly. ") {

		$config = new OllamaConfig();
		//$config->apiKey = $this->apiKey;
		$config->url = $this->url;
		$config->model = $this->model;

		/*
		set temperature to 0.4 (conservative answers) and the context window to 16384 tokens.
		These settings are suitable for most pure-text scenarios even with smaller, Q4 LLMs and
		limited VRAM (e.g. 12 GB)
		TODO make these configurable in a future version (?)
		*/
		$config->modelOptions = array (
			'num_ctx' => '16384',
			'temperature' => '0.4',
		);
		$chat = new OllamaChat($config);

		$chat->setSystemMessage ($sSystemPrompt);
		$response = $chat->generateText($sMessage);
		\IssueLog::Debug(__METHOD__);
		\IssueLog::Debug($response);

		// TODO error handling in LLPhant ( #2) ?
		return $response;
	}

}

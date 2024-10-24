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

use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;

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
		return [
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:GetCompletions',
				'prompt' => 'getCompletions'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:Translate',
				'prompt' => 'translate'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:improveText',
				'prompt' => 'improveText'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:summarizeTicket',
				'prompt' => 'summarizeTicket'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:recategorizeTicket',
				'prompt' => 'recategorizeTicket'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:autoRecategorizeTicket',
				'prompt' => 'autoRecategorizeTicket'
			],
			[
				'label' => 'UI:AIResponse:OpenAI:Prompt:determineType',
				'prompt' => 'determineType'
			]
		];
	}


	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): OpenAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.openai.com/v1/chat/completions';
		$model = $configuration['model'] ?? 'gpt-3.5-turbo';
		$languages = $configuration['translate_languages'] ?? ['German', 'English', 'French'];
		$aSystemPrompts = $configuration['system_prompts'] ?? [];
		$apiKey = $configuration['api_key'] ?? [];
		return new self($url, $apiKey, $model, $languages, $aSystemPrompts);
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
		$oResult = $this->sendRequest([
			'model' => $this->model,
			'messages' => [
				[
					'role' =>  'system',
					'content' => $sSystemPrompt
				],
				[
					'role' => 'user',
					'content' => $sMessage
				]
			],
			'stream' => false,
			'temperature' => 0.4,
			'num_ctx' => 16384,
		]);
		//TODO Check result

		// access key information of response
		$oResultMessage = $oResult->choices[0]->message;

		// error handling
		if ($oResultMessage->role != "assistant") {
			throw new AIResponseException("Invalid AI response");
		}
		// body of response
		return $oResultMessage->content;
	}

	/**
	 * send post request via curl to OpenAI
	 *
	 * @param array $postData
	 * @return mixed
	 * @throws AIResponseException
	 */
	protected function sendRequest($postData)
	{
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($postData),
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer '.$this->apiKey,
			),
		));

		$response = curl_exec($curl);
		\IssueLog::Info(json_encode($postData));
		\IssueLog::Info(__METHOD__);
		\IssueLog::Info($response);
		$iErr = curl_errno($curl);
		$sErrMsg = curl_error($curl);
		if ($iErr !== 0) {
			throw new AIResponseException("Problem opening URL: $this->url, $sErrMsg");
		}
		return json_decode($response,false);
	}

}

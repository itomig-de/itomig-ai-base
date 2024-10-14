<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
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

class MistralAIEngine implements iAIEngineInterface
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
		return [
			[
				'label' => 'UI:AIResponse:MistralAI:Prompt:GetCompletions',
				'prompt' => 'getCompletions'
			],
			[
				'label' => 'UI:AIResponse:MistralAI:Prompt:Translate',
				'prompt' => 'translate'
			],
			[
				'label' => 'UI:AIResponse:MistralAI:Prompt:improveText',
				'prompt' => 'improveText'
			]
		];
	}

	/**
	 * @inheritDoc
	 */
	public static function GetEngine($configuration): MistralAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.mistral.ai/v1/chat/completions';
		$model = $configuration['model'] ?? 'mistral-large-latest';
		$languages = $configuration['translate_languages'] ?? ['German', 'English', 'French'];
		$apiKey = $configuration['api_key'] ?? '';
		return new self($url, $apiKey, $model, $languages);
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
	 * @param string $url
	 * @param string $apiKey
	 * @param string $model
	 * @param string[] $languages
	 */
	public function __construct($url, $apiKey, $model, $languages)
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
		$this->languages = $languages;
	}

	/**
	 * @inheritDoc
	 * @throws AIResponseException
	 */
	public function PerformPrompt($prompt, $text): string
	{
		switch ($prompt)
		{
			case 'translate':
				return $this->translate($text);

			case 'improveText':
				return $this->improveText($text);

			default:
				return $this->getCompletions($text);
		}
	}

	/**
	 * Ask Mistral AI to translate text
	 *
	 * @param string $sMessage
	 * @param string $language
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	protected function translate($sMessage, $language = "English") {
		// is the language supported?
		if (!in_array($language, $this->languages)) {
			throw new AIResponseException("Invalid language \"$language\"");
		}
		return $this->getCompletions("Translate the following text into the language ".$language.". Only translate the text, do not make any comment about the translation, do not add any placeholders. Text: ".$sMessage );
	}

	/**
	 * Ask Mistral AI to improve text
	 *
	 * @param $sMessage
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	protected function improveText($sMessage) {
		return $this->getCompletions("Improve the following text, without changing its original language, by making it more polite and correcting grammatical and orthographic errors. Do not provide explanations about the improvements or changes you made: ".$sMessage);
	}

	/**
	 * Ask Mistral AI a question, retrieve the answer and return it in text form
	 *
	 * @param $sMessage
	 * @return string the textual response
	 * @throws AIResponseException
	 */
	protected function getCompletions($sMessage) {
		$oResult = $this->sendRequest([
			'model' => $this->model,
			'messages' => [
				[
					'role' => 'user',
				//	'response_format' => ['type' => 'json_object'], /* Jul24: Mistral API no longer permits this parameter */
					'content' => $sMessage
				]
			]
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
	 * send post request via curl to mistral
	 *
	 * @param array $postData
	 * @return mixed
	 * @throws AIResponseException
	 */
	protected function sendRequest($postData)
	{
		/*
		 '{
            "model": '.$sModel.',
            "messages": [
            {
                "role": "user",
                "response_format": {"type": "json_object"},
                "content": '.$sMessage.'
            }
            ]
        }'
		 */

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

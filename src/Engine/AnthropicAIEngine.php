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

class AnthropicAIEngine implements iAIEngineInterface
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
        return [
            [
                'label' => 'UI:AIResponse:AnthropicAI:Prompt:GetCompletions',
                'prompt' => 'getCompletions'
            ],
            [
                'label' => 'UI:AIResponse:AnthropicAI:Prompt:Translate',
                'prompt' => 'translate'
            ],
            [
                'label' => 'UI:AIResponse:AnthropicAI:Prompt:improveText',
                'prompt' => 'improveText'
            ]
        ];
    }

    /**
     * @inheritDoc
     */
	public static function GetEngine($configuration): AnthropicAIEngine
	{
		$url = $configuration['url'] ?? 'https://api.anthropic.com/v1/messages';
		$model = $configuration['model'] ?? 'claude-3-sonnet-20240229';
		$languages = $configuration['translate_languages'] ?? ['German', 'English', 'French'];
		$apiKey = $configuration['api_key'] ?? '';
		$aSystemPrompts = $configuration['system_prompts'] ?? ['translate' =>
			'You are a professional translator. \
			You translate any given text into the language that is being indicated to you. \
			If no language is indicated, you translate into German.',
			'improveText' =>
				'You are a helpful professional writing assistant. \
			You improve any given text by making it polite and professional, without changing its meaning nor its original language. ',
			'default' => 'You are a helpful assistant. You respond in a polite, professional way and keep your responses concise. \
		      Your responses are in the same language as the question.'];
		return new self($url, $apiKey, $model, $languages, $aSystemPrompts );
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
	protected $aSystemPrompts;

	/**
	 * @param string $url
	 * @param string $apiKey
	 * @param string $model
	 * @param string[] $languages
	 * @param array $aSystemPrompts
	 */
	public function __construct($url, $apiKey, $model, $languages, $aSystemPrompts = array (
		'translate' =>
			'You are a professional translator. \
			You translate any given text into the language that is being indicated to you. \
			If no language is indicated, you translate into German.',
		'improveText' =>
			'You are a helpful professional writing assistant. \
			You improve any given text by making it polite and professional, without changing its meaning or its original language. ',
		'default' => 'You are a helpful assistant. You respond in a polite, professional way and keep your responses concise. \
		      Your responses are in the same language as the question.'
	))
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
		$this->languages = $languages;
		$this->aSystemPrompts = $aSystemPrompts;
	}

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
                return $this->getCompletions($text);
        }
    }

    /**
     * Ask Anthropic AI to translate text
     *
     * @param string $sMessage
     * @param string $language
     * @return string the textual response
     * @throws AIResponseException
     */
    protected function translate($sMessage, $language = "English") {
        if (!in_array($language, $this->languages)) {
            throw new AIResponseException("Invalid language \"$language\"");
        }
        return $this->getCompletions("Translate the following text into $language. Only translate the text, do not make any comment about the translation, do not add any placeholders: $sMessage");
    }

    /**
     * Ask Anthropic AI to improve text
     *
     * @param $sMessage
     * @return string the textual response
     * @throws AIResponseException
     */
    protected function improveText($sMessage) {
        return $this->getCompletions("Improve the following text, without changing its original language, by making it more polite and correcting grammatical and orthographic errors. Do not provide explanations about the improvements or changes you made: $sMessage");
    }

    /**
     * Ask Anthropic AI a question, retrieve the answer and return it in text form
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
                    'content' => $sMessage
                ]
            ],
            'max_tokens' => 1000
        ]);

        if (!isset($oResult->content) || !isset($oResult->content[0]->text)) {
            throw new AIResponseException("Invalid AI response structure");
        }

        return $oResult->content[0]->text;
    }

    /**
     * send post request via curl to Anthropic
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
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ),
        ));

        $response = curl_exec($curl);
        $iErr = curl_errno($curl);
        $sErrMsg = curl_error($curl);
        curl_close($curl);

        if ($iErr !== 0) {
            throw new AIResponseException("Problem opening URL: $this->url, $sErrMsg");
        }

        return json_decode($response);
    }
}

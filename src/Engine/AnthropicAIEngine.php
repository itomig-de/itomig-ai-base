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
		$model = $configuration['model'] ?? 'claude-3-sonnet-20240229';
		$languages = $configuration['translate_languages'] ?? ['German', 'English', 'French'];
		$apiKey = $configuration['api_key'] ?? '';
		$aSystemPrompts = $configuration['system_prompts'] ?? [];
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
     * Ask Anthropic AI a question, retrieve the answer and return it in text form
     *
     * @param $sMessage
     * @param $sSystemPrompt 
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
            'max_tokens' => 5000,
            'temperature' => 0.4,
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

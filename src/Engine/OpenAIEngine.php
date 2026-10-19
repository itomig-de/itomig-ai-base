<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
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

use GuzzleHttp\Exception\ConnectException;
use Itomig\iTop\Extension\AIBase\Contracts\iAIVisionEngine;
use IssueLog;
use Itomig\iTop\Extension\AIBase\Exception\AINetworkException;
use Itomig\iTop\Extension\AIBase\Exception\AIInvalidImageException;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\Message;
use LLPhant\Chat\Vision\ImageQuality;
use LLPhant\Chat\Vision\ImageSource;
use LLPhant\Chat\Vision\VisionMessage;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;

class OpenAIEngine extends GenericAIEngine implements iAIEngineInterface, iAIVisionEngine
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
	public static function GetEngine(array $configuration): OpenAIEngine
	{
		$url = $configuration['url'] ?? '';
		$model = $configuration['model'] ?? 'gpt-4o-mini';
		$apiKey = $configuration['api_key'] ?? '';
		$supportsVision = self::GetConfiguredVisionSupport($configuration);

		return new self($url, $apiKey, $model, $supportsVision);
	}

	public function __construct(
		string $url,
		string $apiKey,
		string $model,
		bool $supportsVision = false
	) {
		parent::__construct($url, $apiKey, $model, $supportsVision);
	}


	/**
	 * Ask OpenAI a question, retrieve the answer and return it in text form
	 *
	 * @param string $message
	 * @param string $systemInstruction optional - the System prompt (if a specific one is required)
	 * @return string the textual response
	 */
	public function GetCompletion(string $message, string $systemInstruction = '') : string
	{
		IssueLog::Debug("OpenAIEngine: getCompletions() called");
		$oChat = $this->createChatInstance();
		$oChat->setSystemMessage($systemInstruction);

		IssueLog::Debug("OpenAIEngine: system Message set, next step: generateText()..");
		try {
			$response = $oChat->generateText($message);
		} catch (\LLPhant\Exception\HttpException $e) {
			throw $this->classifyHttpException($e);
		} catch (ConnectException $e) {
			throw new AINetworkException('AI engine unreachable: ' . $e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			throw new AINetworkException('Unexpected AI engine error: ' . $e->getMessage(), 0, $e);
		}
		IssueLog::Debug(__METHOD__);
		IssueLog::Debug($response);
		return $response;
	}

	/**
	 * Creates and returns an instance of OpenAIChat.
	 *
	 * @return ChatInterface
	 */
	protected function createChatInstance(): ChatInterface
	{
		$oConfig = new OpenAIConfig();
		$oConfig->apiKey = $this->apiKey;
		if (!empty($this->url)) {
			$oConfig->url = $this->url;
		}
		if (!empty($this->model)) {
			$oConfig->model = $this->model;
		}
		$oChat = new OpenAIChat($oConfig);
		return $oChat;
	}

	public function CreateVisionMessage(string $sContent, array $aImages): Message
	{
		$aImages = $this->NormalizeVisionImages($aImages);
		$aImageSources = [];

		foreach ($aImages as $aImage) {
			try {
				$aImageSources[] = new ImageSource(
					$aImage['data'],
					ImageQuality::Auto
				);
			} catch (\InvalidArgumentException $e) {
				throw new AIInvalidImageException(
					'Unable to construct the OpenAI image payload.',
					0,
					$e
				);
			}
		}

		return VisionMessage::fromImages(
			$aImageSources,
			$sContent
		);
	}
}

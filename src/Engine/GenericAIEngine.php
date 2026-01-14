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

use Dict;
use IssueLog;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\Enums\ChatRole;
use LLPhant\Chat\Message;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\OpenAIChat;

abstract class GenericAIEngine implements iAIEngineInterface
{
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

	public function __construct($url, $apiKey, $model)
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
	}

	/**
	 * Abstract method that concrete engine classes must implement to provide
	 * their specific llphant chat instance.
	 *
	 * @return ChatInterface
	 */
	abstract protected function createChatInstance(): ChatInterface;

	/**
	 * Generic implementation for handling a conversational turn.
	 * This method uses the Template Method Pattern, relying on createChatInstance from subclasses.
	 *
	 * @param Message[] $aHistory
	 * @return string
	 */
	public function GetNextTurn(array $aHistory): string
	{
		$oChat = $this->createChatInstance();
		$sSystemMessage = '';
		$aMessageHistory = [];

		// Extract the FIRST (and only) System-Message if present
		// (System-Message was set by ContinueConversation as first message)
		if (!empty($aHistory) && $aHistory[0]->role === ChatRole::System) {
			$sSystemMessage = $aHistory[0]->content;
			$aHistory = array_slice($aHistory, 1); // Remove for generateChat()
		}

		// Defense in Depth: Verify no additional system messages leaked through
		foreach ($aHistory as $oMessage) {
			if ($oMessage->role === ChatRole::System) {
				// This should NEVER happen - system messages should have been filtered in ContinueConversation
				IssueLog::Error(__METHOD__ . ": System message leaked into history after filtering!", AIBaseHelper::MODULE_CODE);
				continue; // Skip - do not add
			}
			$aMessageHistory[] = $oMessage;
		}

		if (!empty($sSystemMessage)) {
			$oChat->setSystemMessage($sSystemMessage);
		}

		IssueLog::Debug(__METHOD__ . ": Calling AI Engine with a conversation history of " . count($aMessageHistory) . " turns.", AIBaseHelper::MODULE_CODE);
		$sResponse = $oChat->generateChat($aMessageHistory);
		IssueLog::Debug(__METHOD__ . ": AI Response received.", AIBaseHelper::MODULE_CODE);
		return $sResponse;
	}
}


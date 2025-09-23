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

use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use IssueLog;
use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\Message;
use LLPhant\Chat\OpenAIChat;

abstract class GenericAIEngine implements iAIEngineInterface
{
	protected string $sAPIKey;
	protected string $sModel;

	public function __construct($sAPIKey, $sModel)
	{
		$this->sAPIKey = $sAPIKey;
		$this->sModel = $sModel;
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

		// Separate the system message from the rest of the history for llphant
		foreach ($aHistory as $oMessage) {
			if ($oMessage->role === \LLPhant\Chat\Enums\ChatRole::System) {
				$sSystemMessage = $oMessage->content;
			} else {
				$aMessageHistory[] = $oMessage;
			}
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


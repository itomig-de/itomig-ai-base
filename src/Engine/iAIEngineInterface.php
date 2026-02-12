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

use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\Message;

interface iAIEngineInterface
{
	/**
	 * Get name of the engine
	 * @return string
	 */
	public static function GetEngineName() : string;

	/**
	 * Create an instance of the current engine
	 * @param array $configuration
	 * @return iAIEngineInterface
	 */
	public static function GetEngine($configuration) : iAIEngineInterface;

	/**
	 * Perform prompt and return result
	 * @param string $message
	 * @param string $systemInstruction
	 * @return string
	 */
	public function GetCompletion($message, $systemInstruction = '') : string;

	/**
	 * Generates the next response in a conversation given the full message history.
	 *
	 * @param Message[] $aHistory The entire conversation history as llphant Message objects.
	 * @param FunctionInfo[] $aTools Optional array of FunctionInfo objects for function calling.
	 *                               If provided, the AI can call these tools during the conversation.
	 * @return string|FunctionInfo[] String for text response, FunctionInfo[] for tool calls
	 */
	public function GetNextTurn(array $aHistory, array $aTools = []): string|array;
}

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

use Itomig\iTop\Extension\AIBase\Exception\AIConfigurationException;
use LLPhant\Chat\Message;

interface iAIEngineInterface
{
	/**
	 * @param array $aConfiguration
	 * @return mixed
	 */
	public static function GetEngine($aConfiguration);

	/**
	 * @return string
	 */
	public static function GetEngineName(): string;

	/**
	 * @param string $sMessage
	 * @param string $sSystemInstruction
	 * @return string
	 */
	public function GetCompletion($sMessage, $sSystemInstruction = ''): string;

	/**
	 * Generates the next response in a conversation given the full message history.
	 * @param Message[] $aHistory The entire conversation history as llphant Message objects.
	 * @return string The AI's response message.
	 */
	public function GetNextTurn(array $aHistory): string;
}

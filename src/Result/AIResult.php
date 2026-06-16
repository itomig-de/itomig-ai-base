<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
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

namespace Itomig\iTop\Extension\AIBase\Result;

/**
 * Value object returned by AIService::ContinueConversation().
 *
 * Implements ArrayAccess so existing callers using $result['response']
 * and $result['history'] continue to work without modification.
 * New callers may use the typed properties $result->response and $result->history directly.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class AIResult implements \ArrayAccess
{
	/** The AI-generated text response, with think-tags removed. */
	public readonly string $response;

	/**
	 * The clean conversation history to be stored by the caller.
	 * Contains only user and assistant messages (no system messages).
	 *
	 * @var array<int, array{role: string, content: string}>
	 */
	public readonly array $history;

	/**
	 * @param string $response The AI-generated text response
	 * @param array<int, array{role: string, content: string}> $history The clean conversation history
	 */
	public function __construct(string $response, array $history)
	{
		$this->response = $response;
		$this->history  = $history;
	}

	// -------------------------------------------------------------------------
	// ArrayAccess — backwards compatibility for callers using $result['key']
	// -------------------------------------------------------------------------

	public function offsetExists(mixed $offset): bool
	{
		return in_array($offset, ['response', 'history'], true);
	}

	public function offsetGet(mixed $offset): mixed
	{
		return match ($offset) {
			'response' => $this->response,
			'history'  => $this->history,
			default    => null,
		};
	}

	/** Read-only value object — setting values is not supported. */
	public function offsetSet(mixed $offset, mixed $value): void
	{
	}

	/** Read-only value object — unsetting values is not supported. */
	public function offsetUnset(mixed $offset): void
	{
	}
}

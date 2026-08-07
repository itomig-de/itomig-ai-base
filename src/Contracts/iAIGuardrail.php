<?php
/*
 * @copyright Copyright (C) 2026 ITOMIG GmbH
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

namespace Itomig\iTop\Extension\AIBase\Contracts;

use Itomig\iTop\Extension\AIBase\Result\GuardrailVerdict;

/**
 * Interface for content guardrails that screen AI input and output.
 *
 * Implement this interface in your extension to have every AI call routed through
 * a content check. Implementations are discovered automatically via iTop's
 * InterfaceDiscovery mechanism, so no registration step is required.
 *
 * AIService calls IsEnabledFor() before every Check(). Keep IsEnabledFor() cheap
 * and free of I/O: it is the only thing standing between a disabled guardrail and
 * a needless round trip on every single AI call.
 *
 * Guardrails must not be trusted to be reliable. AIService treats any exception
 * thrown by an implementation as fail-open: the AI call proceeds and the failure
 * is logged. Blocking is expressed through the returned verdict, not by throwing.
 *
 * Example implementation:
 * ```php
 * class MyGuardrail implements iAIGuardrail
 * {
 *     public function IsEnabledFor(string $sSurface, string $sDirection): bool
 *     {
 *         return $sDirection === self::DIRECTION_INPUT;
 *     }
 *
 *     public function Check(string $sContent, string $sSurface, string $sDirection,
 *                           array $aContext = []): GuardrailVerdict
 *     {
 *         if (str_contains($sContent, 'forbidden')) {
 *             return new GuardrailVerdict(true, [[
 *                 'policy_code' => 'my_policy',
 *                 'matched'     => true,
 *                 'score'       => null,
 *                 'mode'        => 'block',
 *                 'message'     => 'Content matched my_policy',
 *             ]]);
 *         }
 *         return GuardrailVerdict::Pass();
 *     }
 * }
 * ```
 */
interface iAIGuardrail
{
	/**
	 * Content submitted to the model: user messages, prompts, ticket data.
	 */
	public const DIRECTION_INPUT = 'input';

	/**
	 * Content produced by the model and about to be handed back to a caller.
	 */
	public const DIRECTION_OUTPUT = 'output';

	/**
	 * Content returned by a tool call, before it re-enters the conversation history.
	 * This is the main surface for indirect prompt injection ("tool poisoning").
	 */
	public const DIRECTION_TOOL_RESULT = 'tool_result';

	/**
	 * Whether this guardrail wants to inspect the given surface and direction at all.
	 *
	 * Must be cheap and must not perform I/O — it runs on every AI call, including
	 * those of callers that never enabled a guardrail.
	 *
	 * @param string $sSurface Caller-declared context, e.g. 'chat', 'ticket.summarize'.
	 *                         Defaults to 'default' when the caller did not declare one.
	 * @param string $sDirection One of the DIRECTION_* constants.
	 * @return bool
	 */
	public function IsEnabledFor(string $sSurface, string $sDirection): bool;

	/**
	 * Screens a piece of content and returns a verdict.
	 *
	 * Implementations should return a verdict rather than throw. Exceptions are
	 * treated as guardrail failures and handled fail-open by AIService.
	 *
	 * @param string $sContent The content to screen.
	 * @param string $sSurface Caller-declared context, see IsEnabledFor().
	 * @param string $sDirection One of the DIRECTION_* constants.
	 * @param array $aContext Optional additional context, e.g. ['object_class' => 'UserRequest', 'object_key' => 42].
	 * @return GuardrailVerdict
	 */
	public function Check(string $sContent, string $sSurface, string $sDirection, array $aContext = []): GuardrailVerdict;
}

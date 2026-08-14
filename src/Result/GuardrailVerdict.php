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

namespace Itomig\iTop\Extension\AIBase\Result;

/**
 * Value object returned by iAIGuardrail::Check().
 *
 * A verdict carries two independent pieces of information: whether the AI call must
 * be stopped ($blocked), and which policies were evaluated with what outcome
 * ($violations). A guardrail running in audit mode reports violations while leaving
 * $blocked false — that combination is the normal case during policy calibration.
 */
class GuardrailVerdict
{
	/** Whether the AI call must be aborted. */
	public readonly bool $blocked;

	/**
	 * The policies that matched, most relevant first.
	 *
	 * @var array<int, array{policy_code: string, matched: bool, score: float|null, mode: string, message: string}>
	 */
	public readonly array $violations;

	/**
	 * @param bool $blocked Whether the AI call must be aborted
	 * @param array<int, array{policy_code: string, matched: bool, score: float|null,
	 *                         mode: string, message: string}> $violations
	 */
	public function __construct(bool $blocked, array $violations = [])
	{
		$this->blocked    = $blocked;
		$this->violations = $violations;
	}

	/**
	 * The "nothing to report" verdict.
	 *
	 * Used as the fast path when no guardrail is installed, when a guardrail opts out
	 * of a surface, and when a check completes without any policy matching.
	 *
	 * @return self
	 */
	public static function Pass(): self
	{
		return new self(false, []);
	}

	/**
	 * Whether any policy matched, regardless of whether it caused a block.
	 *
	 * @return bool
	 */
	public function HasViolations(): bool
	{
		return $this->violations !== [];
	}

	/**
	 * The codes of all matched policies, for logging and error messages.
	 *
	 * @return string[]
	 */
	public function GetViolatedPolicyCodes(): array
	{
		return array_map(
			static fn (array $aViolation): string => $aViolation['policy_code'],
			$this->violations
		);
	}
}

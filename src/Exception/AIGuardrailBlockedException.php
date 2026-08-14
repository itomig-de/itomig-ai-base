<?php
/*
 * @copyright Copyright (C) 2026 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
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

namespace Itomig\iTop\Extension\AIBase\Exception;

use Itomig\iTop\Extension\AIBase\Result\GuardrailVerdict;

/**
 * Thrown when a guardrail blocks an AI call because content matched a policy
 * configured in blocking mode.
 *
 * This is not an engine failure: the AI provider was never contacted, or its answer
 * was discarded. Callers should present this to the user as a policy decision rather
 * than as a technical error.
 */
class AIGuardrailBlockedException extends AIEngineException
{
	/** The verdict that caused the block. */
	private GuardrailVerdict $oVerdict;

	/** One of the iAIGuardrail::DIRECTION_* constants. */
	private string $sDirection;

	/**
	 * @param GuardrailVerdict $oVerdict The verdict that caused the block
	 * @param string $sDirection One of the iAIGuardrail::DIRECTION_* constants
	 * @param string $sMessage Optional message; a summary is generated when empty
	 */
	public function __construct(GuardrailVerdict $oVerdict, string $sDirection, string $sMessage = '')
	{
		$this->oVerdict   = $oVerdict;
		$this->sDirection = $sDirection;

		if ($sMessage === '') {
			$sMessage = sprintf(
				'AI call blocked by guardrail on %s: %s',
				$sDirection,
				implode(', ', $oVerdict->GetViolatedPolicyCodes())
			);
		}

		parent::__construct($sMessage);
	}

	/**
	 * @return GuardrailVerdict
	 */
	public function GetVerdict(): GuardrailVerdict
	{
		return $this->oVerdict;
	}

	/**
	 * @return string One of the iAIGuardrail::DIRECTION_* constants
	 */
	public function GetDirection(): string
	{
		return $this->sDirection;
	}

	/**
	 * The codes of the policies that caused the block, for UI messages and logging.
	 *
	 * @return string[]
	 */
	public function GetViolatedPolicyCodes(): array
	{
		return $this->oVerdict->GetViolatedPolicyCodes();
	}
}

<?php

/**
 *  @copyright   Copyright (C) 2010-2026 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Contracts;

use LLPhant\Chat\Message;

interface iAIVisionEngine
{
	/**
	 * Creates a provider-compatible user message containing images.
	 *
	 * @param string $sContent
	 * @param array<int, array{data: string, media_type: string}> $aImages
	 */
	public function CreateVisionMessage(
		string $sContent,
		array $aImages
	): Message;
}
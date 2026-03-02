<?php

/**
 *  @copyright   Copyright (C) 2010-2026 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Engine\Embedding;

use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

interface iEmbeddingEngineInterface
{
	/**
	 * Get name of the engine
	 * @return string
	 */
	public static function GetEngineName(): string;

	/**
	 * Create an instance of the current engine
	 *
	 * @param array $configuration
	 *
	 * @return iAIEngineInterface
	 */
	public static function GetEngine(array $configuration): iEmbeddingEngineInterface;

	public function GetEmbeddingGenerator(): EmbeddingGeneratorInterface;

}

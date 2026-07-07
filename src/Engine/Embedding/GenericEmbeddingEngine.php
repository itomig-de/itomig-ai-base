<?php

/**
 *  @copyright   Copyright (C) 2010-2026 Combodo SARL
 *  @author      Amine Ait-Ali (Combodo)
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Engine\Embedding;

use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

abstract class GenericEmbeddingEngine implements iEmbeddingEngineInterface
{
	protected string $sUrl;
	protected string $sApiKey;
	protected string $sModel;
	protected int $iDimensions;

	public function __construct(string $sUrl, string $sApiKey, string $sModel, int $iDimensions)
	{
		$this->sUrl = $sUrl;
		$this->sApiKey = $sApiKey;
		$this->sModel = $sModel;
		$this->iDimensions = $iDimensions;
	}

	abstract public function GetEmbeddingGenerator(): EmbeddingGeneratorInterface;

}
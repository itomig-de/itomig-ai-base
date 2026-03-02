<?php

/**
 *  @copyright   Copyright (C) 2010-2026 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Engine\Embedding;

use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

abstract class GenericEmbeddingEngine implements iEmbeddingEngineInterface
{
	protected string $url;
	protected string $apiKey;
	protected string $model;
	protected int $dimensions;

	public function __construct($url, $apiKey, $model, $dimensions)
	{
		$this->url = $url;
		$this->apiKey = $apiKey;
		$this->model = $model;
		$this->dimensions = $dimensions;
	}

	abstract public function GetEmbeddingGenerator(): EmbeddingGeneratorInterface;

}

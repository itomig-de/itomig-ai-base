<?php

/**
 * @copyright   Copyright (C) 2010-2026 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Engine\Embedding;

use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\AbstractOpenAIEmbeddingGenerator;
use LLPhant\OpenAIConfig;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class OpenAICompatibleGenerator extends AbstractOpenAIEmbeddingGenerator
{
	public int $batch_size_limit = 25;

	protected string $uri = 'https://openrouter.ai/api/v1';

	private string $sModel;
	private int $iDim;

	public function __construct(
		OpenAIConfig $config,
		string $sModel,
		int $iDim,
		?RequestFactoryInterface $requestFactory = null,
		?StreamFactoryInterface $streamFactory = null
	) {
		parent::__construct($config, $requestFactory, $streamFactory);
		$this->sModel = $sModel;
		$this->iDim = $iDim;
	}

	public function getEmbeddingLength(): int
	{
		return $this->iDim;
	}

	public function getModelName(): string
	{
		return $this->sModel;
	}
}

<?php

/**
 *  @copyright   Copyright (C) 2010-2026 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\Extension\AIBase\Engine\Embedding;

use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3LargeEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAIADA002EmbeddingGenerator;
use LLPhant\OpenAIConfig;

class OpenAIEmbeddingEngine extends GenericEmbeddingEngine implements iEmbeddingEngineInterface
{
	public static function GetEngineName(): string
	{
		return 'OpenAI';
	}

	public static function GetEngine(array $configuration): iEmbeddingEngineInterface
	{
		return new self(
			$configuration['url'] ?? '',
			$configuration['api_key'] ?? '',
			$configuration['model'] ?? 'text-embedding-3-small',
			$configuration['dimensions'] ?? 0,
		);
	}

	/**
	 * @throws \Exception
	 */
	public function GetEmbeddingGenerator(): EmbeddingGeneratorInterface
	{
		$config = new OpenAIConfig();
		$config->apiKey = $this->apiKey;
		if (!empty($this->url)) {
			$config->url = $this->url;
		}
		$config->model = $this->model;

		return match ($this->model) {
			'text-embedding-ada-002' => new OpenAIADA002EmbeddingGenerator($config),
			'text-embedding-3-small' => new OpenAI3SmallEmbeddingGenerator($config),
			'text-embedding-3-large' => new OpenAI3LargeEmbeddingGenerator($config),
			default => new OpenAICompatibleGenerator($config, $this->model, $this->dimensions),
		};
	}
}

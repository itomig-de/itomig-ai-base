<?php

namespace Itomig\iTop\Extension\AIBase\Service;

use Itomig\iTop\Extension\AIBase\Engine\Embedding\iEmbeddingEngineInterface;
use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

/**
 * @copyright   Copyright (C) 2010-2025 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

class EmbeddingService
{
	private EmbeddingGeneratorInterface $embeddingGenerator;

	public function __construct(?iEmbeddingEngineInterface $engine = null)
	{
		$this->embeddingGenerator = $engine->GetEmbeddingGenerator();
	}

	public function GetEmbedding($sMessage): array
	{
		return $this->embeddingGenerator->embedText($sMessage);
	}

	public function GetEmbeddingGeneratorMaxBatchSize(): int
	{
		if (isset($this->embeddingGenerator->batch_size_limit)) {
			return $this->embeddingGenerator->batch_size_limit;
		}
		return 25;
	}

	public function GetEmbeddingLength(): int
	{
		return $this->embeddingGenerator->getEmbeddingLength();

	}

	public function GetEmbeddingsForTexts(array $aTexts): array
	{
		$aDocuments = [];
		foreach ($aTexts as $i => $sText) {
			$oDoc = new Document();
			$oDoc->content = $sText;
			$aDocuments[$i] = $oDoc;
		}

		$aDocuments = $this->embeddingGenerator->embedDocuments($aDocuments);

		$aEmbeddings = [];
		foreach ($aDocuments as $i => $oDoc) {
			$aEmbeddings[$i] = $oDoc->embedding;
		}
		return $aEmbeddings;
	}

	public function GetEmbeddingsForChunkedTexts(array $aChunkedTexts): array
	{
		$aArrayDocuments = [];

		foreach ($aChunkedTexts as $i => $aText) {
			foreach ($aText as $chunkNumber => $sText) {
				$oDoc = new Document();
				$oDoc->content = $sText;
				$oDoc->chunkNumber = $chunkNumber;
				$aArrayDocuments[$i][$chunkNumber] = $oDoc;
			}
		}

		foreach ($aArrayDocuments as $i => $oDoc) {
			$aArrayDocuments[$i] = $this->embeddingGenerator->embedDocuments($oDoc);
		}

		$aArrayEmbeddings = [];
		foreach ($aArrayDocuments as $i => $aDocs) {
			foreach ($aDocs as $chunkNumber => $oDoc) {
				$aArrayEmbeddings[$i][$chunkNumber] = $oDoc->embedding;
			}
		}

		return $aArrayEmbeddings;

	}

}

<?php
/**
 * @license     http://opensource.org/licenses/AGPL-3.0
 * @author      Amine Ait-Ali (Combodo)
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Engine\Embedding\iEmbeddingEngineInterface;
use Itomig\iTop\Extension\AIBase\Service\EmbeddingService;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;

class EmbeddingServiceTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function testGetEmbeddingReturnsGeneratorEmbedding(): void
	{
		$oGenerator = $this->createMock(EmbeddingGeneratorInterface::class);
		$oGenerator->expects(static::once())
			->method('embedText')
			->with('test message')
			->willReturn([0.11, 0.22]);

		$oEngine = $this->createMock(iEmbeddingEngineInterface::class);
		$oEngine->expects(static::once())
			->method('GetEmbeddingGenerator')
			->willReturn($oGenerator);

		$oService = new EmbeddingService($oEngine);

		$aEmbedding = $oService->GetEmbedding('test message');

		static::assertEquals([0.11, 0.22], $aEmbedding);
	}

	public function testGetEmbeddingsForTextsReturnsEmbeddingsByInputKey(): void
	{
		$oGenerator = $this->createMock(EmbeddingGeneratorInterface::class);
		$oGenerator->expects(static::once())
			->method('embedDocuments')
			->willReturnCallback(function(array $aDocuments) {
				static::assertSame([3, 7], array_keys($aDocuments));
				static::assertEquals('first text', $aDocuments[3]->content);
				static::assertEquals('second text', $aDocuments[7]->content);

				$aDocuments[3]->embedding = [1.0, 1.1];
				$aDocuments[7]->embedding = [2.0, 2.2];

				return $aDocuments;
			});

		$oEngine = $this->createMock(iEmbeddingEngineInterface::class);
		$oEngine->expects(static::once())
			->method('GetEmbeddingGenerator')
			->willReturn($oGenerator);

		$oService = new EmbeddingService($oEngine);

		$aEmbeddings = $oService->GetEmbeddingsForTexts([
			3 => 'first text',
			7 => 'second text',
		]);

		static::assertEquals([
			3 => [1.0, 1.1],
			7 => [2.0, 2.2],
		], $aEmbeddings);
	}

	public function testGetEmbeddingsForChunkedTextsReturnsNestedEmbeddingsByChunkNumber(): void
	{
		$iCall = 0;

		$oGenerator = $this->createMock(EmbeddingGeneratorInterface::class);
		$oGenerator->expects(static::exactly(2))
			->method('embedDocuments')
			->willReturnCallback(function(array $aDocuments) use (&$iCall) {
				$iCall++;

				if ($iCall === 1) {
					static::assertSame([0, 2], array_keys($aDocuments));
					static::assertEquals('doc A chunk 0', $aDocuments[0]->content);
					static::assertEquals(0, $aDocuments[0]->chunkNumber);
					static::assertEquals('doc A chunk 2', $aDocuments[2]->content);
					static::assertEquals(2, $aDocuments[2]->chunkNumber);

					$aDocuments[0]->embedding = [10.0];
					$aDocuments[2]->embedding = [12.0];
				} else {
					static::assertSame([1], array_keys($aDocuments));
					static::assertEquals('doc B chunk 1', $aDocuments[1]->content);
					static::assertEquals(1, $aDocuments[1]->chunkNumber);

					$aDocuments[1]->embedding = [21.0];
				}

				return $aDocuments;
			});

		$oEngine = $this->createMock(iEmbeddingEngineInterface::class);
		$oEngine->expects(static::once())
			->method('GetEmbeddingGenerator')
			->willReturn($oGenerator);

		$oService = new EmbeddingService($oEngine);

		$aEmbeddings = $oService->GetEmbeddingsForChunkedTexts([
			10 => [
				0 => 'doc A chunk 0',
				2 => 'doc A chunk 2',
			],
			11 => [
				1 => 'doc B chunk 1',
			],
		]);

		static::assertEquals([
			10 => [
				0 => [10.0],
				2 => [12.0],
			],
			11 => [
				1 => [21.0],
			],
		], $aEmbeddings);
	}
}

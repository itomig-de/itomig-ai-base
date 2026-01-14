<?php
/**
 * Unit tests for Retry System functionality
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;

class RetrySystemTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * Test that operation succeeds on first attempt
	 */
	public function testExecuteWithRetrySuccessOnFirstAttempt(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			return 'Success';
		};

		// Act
		$result = AIBaseHelper::executeWithRetry($operation, 3, 'TestContext');

		// Assert
		static::assertEquals('Success', $result);
		static::assertEquals(1, $iCallCount, 'Operation should only be called once');
	}

	/**
	 * Test that operation succeeds on second attempt after first failure
	 */
	public function testExecuteWithRetrySuccessOnSecondAttempt(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			if ($iCallCount === 1) {
				throw new \Exception('First attempt failed');
			}
			return 'Success on retry';
		};

		// Act
		$result = AIBaseHelper::executeWithRetry($operation, 3, 'TestContext');

		// Assert
		static::assertEquals('Success on retry', $result);
		static::assertEquals(2, $iCallCount, 'Operation should be called twice');
	}

	/**
	 * Test that exception is thrown when all attempts fail
	 */
	public function testExecuteWithRetryAllAttemptsFail(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			throw new \Exception('Operation failed');
		};

		// Act & Assert
		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Unable to establish a connection to the AI service');

		try {
			AIBaseHelper::executeWithRetry($operation, 3, 'TestContext');
		} finally {
			// Assert that all 3 attempts were made
			static::assertEquals(3, $iCallCount, 'All 3 attempts should have been made');
		}
	}

	/**
	 * Test that InvalidArgumentException is thrown when maxAttempts < 1
	 */
	public function testExecuteWithRetryInvalidMaxAttempts(): void
	{
		// Arrange
		$operation = function() {
			return 'Should not be called';
		};

		// Act & Assert
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('maxAttempts must be at least 1');

		AIBaseHelper::executeWithRetry($operation, 0, 'TestContext');
	}

	/**
	 * Test that retries use exponential backoff (verify correct number of attempts)
	 */
	public function testExecuteWithRetryExponentialBackoffAttempts(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			if ($iCallCount < 3) {
				throw new \Exception('Not yet');
			}
			return 'Success after retries';
		};

		// Act
		$result = AIBaseHelper::executeWithRetry($operation, 5, 'TestContext');

		// Assert
		static::assertEquals('Success after retries', $result);
		static::assertEquals(3, $iCallCount, 'Operation should succeed on 3rd attempt');
	}

	/**
	 * Test with maxAttempts = 1 (no retries)
	 */
	public function testExecuteWithRetryNoRetries(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			return 'Single attempt';
		};

		// Act
		$result = AIBaseHelper::executeWithRetry($operation, 1, 'TestContext');

		// Assert
		static::assertEquals('Single attempt', $result);
		static::assertEquals(1, $iCallCount, 'Operation should only be called once with maxAttempts=1');
	}

	/**
	 * Test that different exception types are handled correctly
	 */
	public function testExecuteWithRetryDifferentExceptionTypes(): void
	{
		// Arrange
		$iCallCount = 0;
		$operation = function() use (&$iCallCount) {
			$iCallCount++;
			if ($iCallCount === 1) {
				throw new \RuntimeException('Runtime error');
			}
			if ($iCallCount === 2) {
				throw new \LogicException('Logic error');
			}
			return 'Success after different exceptions';
		};

		// Act
		$result = AIBaseHelper::executeWithRetry($operation, 3, 'TestContext');

		// Assert
		static::assertEquals('Success after different exceptions', $result);
		static::assertEquals(3, $iCallCount, 'Operation should succeed on 3rd attempt');
	}
}

<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 * @author David Gümbel <david.guembel@itomig.de>
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

namespace Itomig\iTop\Extension\AIBase\Helper;

class AIBaseHelper
{
	public const MODULE_CODE = 'itomig-ai-base';

	/**
	 * Cleans an AI-generated JSON string by removing the surrounding "```json\n" and "\n```" markers (if they are there).
	 *
	 * @param string $sRawString The raw string containing the JSON data with surrounding markers.
	 * @return string The cleaned JSON string without the surrounding markers.
	 */
	public static function cleanJSON(string $sRawString)
	{
		$pattern = '/^```json\n(.*?)\n```$/s';

		$cleanedString = preg_replace($pattern, '$1', $sRawString);

		if ($cleanedString === null || $cleanedString === $sRawString) {
			\IssueLog::Debug("cleanJSON(): no modification necessary to string, returning: ".$sRawString, self::MODULE_CODE);
			return $sRawString;
		}
	    \IssueLog::Debug("cleanJSON(): cleaned a string, result is: ".$cleanedString, self::MODULE_CODE);
		return $cleanedString;
	}


	/**
	 * Removes a <think> tag and its contents if present at the beginning of a string.
	 * The tag detection is case-insensitive. If no <think> tag is found at the start,
	 * the original string is returned unchanged. Needed for "cleaning" output of reasoning models.
	 *
	 * @param string $sRawString The input string that might start with a <think> tag
	 * @return string The string with the initial <think> tag and its contents removed, or unchanged if no initial tag
	 */
	public static function removeThinkTag(string $sRawString)
	{
		$pattern = '/^\s*<think\b[^>]*>.*?<\/think>/is';
		
		$cleanedString = preg_replace($pattern, '', $sRawString);
		
		if ($cleanedString === null) {
			\IssueLog::Debug("removeThinkTag(): preg_replace error occurred, returning original string: ".$sRawString, self::MODULE_CODE);
			return $sRawString;
		}
		
		if ($cleanedString === $sRawString) {
			\IssueLog::Debug("removeThinkTag(): no <think> tag found at start, returning unchanged: ".$sRawString, self::MODULE_CODE);
			return $sRawString;
		}
		
		\IssueLog::Debug("removeThinkTag(): removed initial <think> tag, result is: ".$cleanedString, self::MODULE_CODE);
		return $cleanedString;
	}

	
	/**
	 * Strips HTML tags from a string and decodes HTML entities.
	 *
	 * @param string $sString The input string that may contain HTML tags and entities.
	 * @return string The string with HTML tags removed and HTML entities decoded.
	 */
	public function stripHTML(string $sString) {
		return html_entity_decode(strip_tags($sString));
	}

	/**
	 * Executes an AI operation with automatic retry and exponential backoff.
	 *
	 * This method wraps AI operations (like generateText() or generateChat()) with retry logic
	 * to handle transient failures like network timeouts, rate limiting, or temporary service unavailability.
	 * Uses exponential backoff strategy: 1s, 2s, 4s, 8s...
	 *
	 * @param callable $operation The operation to execute (must return string)
	 * @param int $maxAttempts Number of attempts (default: 3, must be >= 1)
	 * @param string $contextName Context for logging (e.g. 'OpenAIEngine')
	 * @return string The operation result
	 * @throws \InvalidArgumentException If maxAttempts < 1
	 * @throws \Exception If all attempts fail
	 */
	public static function executeWithRetry(callable $operation, int $maxAttempts = 3, string $contextName = 'AI Engine'): string
	{
		if ($maxAttempts < 1) {
			throw new \InvalidArgumentException('maxAttempts must be at least 1');
		}

		for ($i = 0; $i < $maxAttempts; $i++) {
			try {
				$result = $operation();
				\IssueLog::Debug("$contextName: Operation succeeded on attempt ".($i + 1), self::MODULE_CODE);
				return $result;
			}
			catch (\Exception $e) {
				\IssueLog::Error("$contextName: Error during attempt ".($i + 1)."/$maxAttempts: ".$e->getMessage(), self::MODULE_CODE);

				// Exponential Backoff (außer beim letzten Versuch)
				if ($i < $maxAttempts - 1) {
					$waitTime = pow(2, $i);  // 1s, 2s, 4s, 8s...
					\IssueLog::Debug("$contextName: Waiting {$waitTime}s before retry", self::MODULE_CODE);
					sleep($waitTime);
				}
			}
		}

		// Alle Versuche gescheitert
		throw new \Exception(\Dict::S('itomig-ai-base/ErrorAIEngineConnexion'));
	}


}




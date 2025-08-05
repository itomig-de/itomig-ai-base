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



}




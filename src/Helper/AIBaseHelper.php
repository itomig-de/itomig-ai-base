<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
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

use Dict;

class AIBaseHelper
{
	public const MODULE_CODE = 'itomig-ai-base';

	public $oAIEngine = null;


    public function __construct() {
      $oAIService = new \Itomig\iTop\Extension\AIBase\Service\AIService();
      $this->oAIEngine = $oAIService->GetAIEngine();

    }

	/**
	 * Cleans an AI-generated JSON string by removing the surrounding "```json\n" and "\n```" markers (if they are there).
	 *
	 * @param string $sRawString The raw string containing the JSON data with surrounding markers.
	 * @return string The cleaned JSON string without the surrounding markers.
	 */
	public function cleanJSON(string $sRawString)
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
	 * Check if an AI result is within valid parameters (guardrail against (some) hallucinations)
	 * @param $aValidresults array of valid results (e.g. ServiceSubcategories).
	 * @param $sKey $aValidResults[$sKkey] will be checked against $sValue
	 * @param $sValue the value that AI provided, to be determined if valid or not
	 */
	public function isValidResult($aValidResults,$sKey,$sValue) {
		\IssueLog::Debug("isValidResult(): checking if ".$sKey." => ".$sValue." are in valid results range..", self::MODULE_CODE);
		if ($aValidResults[$sKey] == $sValue) return true;
		return false;

	}




}




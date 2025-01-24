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

use Itomig\iTop\Extension\AIBase\Service;
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
			return $sRawString;
		}
	
		return $cleanedString;
	}


	/**
	 * Check if an AI result is within valid parameters (guardrail against (some) hallucinations)
	 * @param $aValidresults array of valid results (e.g. ServiceSubcategories).
	 * @param $sKey $aValidResults[$sKkey] will be checked against $sValue
	 * @param $sValue the value that AI provided, to be determined if valid or not
	 */
	public function isValidResult($aValidResults,$sKey,$sValue) {
		if ($aValidResults[$sKey] == $sValue) return true;
		return false;

	}


	  /**
	   * Autorecategorizes a ticket based on AI analysis.
	   *
	   * This method uses the AI engine to analyze a given ticket and attempts to reassign it to a more appropriate service subcategory.
	   * If the new subcategory is technically valid, the ticket's related attributes are updated accordingly, including service ID,
	   * service subcategory ID, request type, and private log. A success or failure message is set based on the analysis result,
	   * and an optional session message can be displayed if requested.
	   *
	   * @param $oTicket The ticket to be reclassified.
	   * @param bool $bDisplayMessage Whether to display a success message. Default is false.
	   * @return string A success or failure message indicating the outcome of the recategorization attempt.
	   */
	  public function autoRecategorizeTicket($oTicket, $bDisplayMessage = false) {

		  $aResult = $this->oAIEngine->autoRecategorizeTicket($oTicket);

		  // get Service Catalogue for the Ticket Org and only items matching the AI-guessed Request Type
		  $aSerCat = $this->getServiceCatalogue($oTicket->Get('org_id'), $aResult['type'], true );


		  // check if Service Subcategory is technically valid for the Ticket
		  // TODO maybe replace by generic AIHelper function
		  $iSubCatID = $aResult['subcategory']['ID'];
		  foreach ($aSerCat as $aSSC) {
			  if ($aSSC['ID'] == $iSubCatID) {
				  $aResultData = [
					  'service_id' => $aSSC['Service ID'],
					  'servicesubcategory_id' => $iSubCatID,
					  'type' => $aResult['Type'],
					  'rationale' => $aResult['rationale'],
				  ];
				  $oTicket->Set('service_id',$aResultData['service_id']);
				  $oTicket->Set('servicesubcategory_id',$aResultData['servicesubcategory_id']);
				  $oTicket->Set('request_type',$aResultData['type']);
				  $oTicket->Set('private_log', "I made AI recategorize this Ticket. Rationale: ".$aResultData['rationale']);

				  $sLabel = Dict::S('GenericAIEngine:autoRecategorizeTicket:success');
				  $sResult = sprintf($sLabel, $aResultData['rationale']);
				  return $sResult;

			  }
		  }
		  // Failure - do nothing with the ticket, return a message.
		  $sLabel = Dict::S('GenericAIEngine:autoRecategorizeTicket:failure');
		  $sResult = sprintf($sLabel, $iSubCatID);
		  return $sResult;


	  }


}




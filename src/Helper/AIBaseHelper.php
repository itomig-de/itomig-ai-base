<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
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

use Itomig\iTop\Extension\AIBase\Service;
use Dict;

class AIBaseHelper
{
	public const MODULE_CODE = 'itomig-ai-base';

	protected $oAIEngine = null;


    public function __construct() {
      $oAIService = new \Itomig\iTop\Extension\AIBase\Service\AIService();
      $this->oAIEngine = $oAIService->GetAIEngine();

    }

/**
	 * Retrieve Service Catalogue for a customer, one line per Subcategory
	 * @param int $iTicketOrgID org_id of the customer
	 * @param $sRequestType  "incident" or "service_request" (or leave empty for both)
	 * @param bool $bReturnArray
	 * @return array|string
	 * @throws \CoreException
	 */
	public function getServiceCatalogue($iTicketOrgID, $sRequestType = "", $bReturnArray = false) {

		$sTextualSerCat = "";

		// get whole SerCat incl. Service Family, Service, Service Subcategory
		$sQuery = "SELECT ServiceSubcategory AS sc JOIN Service AS s ON sc.service_id=s.id 
            JOIN lnkCustomerContractToService AS l1 ON l1.service_id=s.id 
            JOIN CustomerContract AS cc ON l1.customercontract_id=cc.id 
            WHERE cc.org_id = $iTicketOrgID AND s.status != 'obsolete'";

		// if given, add a filter on request_type (incident, service_request)
		switch ($sRequestType) {
			case "incident":
				$sQuery .= " AND sc.request_type = 'incident'";
			case "service_request":
				$sQuery .= " AND sc.request_type = 'service_request'";
			default: 
			\IssueLog::Debug("getServiceCatalogue(): got invalid sRequestType: ".$sRequestType. ", not applying request_Type filter" . $sPrompt, AIBaseHelper::MODULE_CODE);
		}

		$oResultSet = new \DBObjectSet (\DBObjectSearch::FromOQL($sQuery));
		if ($oResultSet->Count() > 0 ){
			while ($oServiceSubcategory = $oResultSet->Fetch()) {
				$sService = $oServiceSubcategory->Get('service_name');
				$sServiceSubcategory = $oServiceSubcategory->Get('name');
				$iServiceID = $oServiceSubcategory->Get('service_id');
				$sServiceSCDescription = $oServiceSubcategory->Get('description');
				$iServiceSCID = $oServiceSubcategory->GetKey();
				$sRequestType = $oServiceSubcategory->Get('request_type');
				$sTextualSerCat .= "Service-Subcategory-ID: $sServiceSCID #### Service-Subcategory-Name: $sServiceSubcategory #### Service-Name: $sService #### Service-Subcategory-Description: $sServiceSCDescription \n";

				// using [] shorthand for array_push()
				$aSerCat[] = [
					'ID' => $iServiceSCID,
					'Service' => $sService,
					'Service ID' => $iServiceID,
					'Name' => $sServiceSubcategory,
					'Description' => $sServiceSCDescription,
					'Type'=> $sRequestType,
				];
			}
		}

		if ($bReturnArray) return $aSerCat;
		return $sTextualSerCat;

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
	 * Retrieve detailed information about a ticket.
	 * @param $oTicket the Ticket object
	 * @return array with attribute name => value
	 */
	public function getTicketData($oTicket)
	{
		\IssueLog::Info("getTicketData() called");
		// TODO Ticket must have a public_log, which de facto makes this incompatible with anything but UserRequests
		$sPublicLog = $oTicket->Get('public_log');
		$sTitle = $oTicket->Get('title');
		$sRef = $oTicket->Get('ref');
		$sDescription = $oTicket->Get('description');
		$sCaller = $oTicket->Get('caller_id_friendlyname');
		$sOrg = $oTicket->Get('org_id_friendlyname');
		$sStatus = $oTicket->Get('status');
		\IssueLog::Info("getTicketData() has collected all attributes");

		return [
			'ref' => $sRef,
			'title' => $sTitle,
			'description' => $sDescription,
			'caller' => $sCaller,
			'organisation' => $sOrg,
			'status' => $sStatus,
			'public_log' => $sPublicLog,
		];
	}


	/**
	 * Retrieve all child Tickets (UserRequests)
	 * @param $oTicket the parent Ticket object
	 * @return array with attribute name => value
	 */
	public function getChildTickets($oTicket)
	{
		\IssueLog::Info("getChildTickets() called", AIBaseHelper::MODULE_CODE);
		$aChildTicketList = array();
		$sTicketClass = $oTicket->Get('finalclass');
		$iTicketID= $oTicket->Get('id');
		// build query string
		switch ($sTicketClass){
			case "UserRequest":
				$sQuery = "SELECT UserRequest AS t WHERE t.parent_request_id = $iTicketID";
/*			case "Problem":
				$sQuery = "SELECT Problem AS t WHERE t.parent_problem_id = $iTicketID";
			case "Change":
					$sQuery = "SELECT Change AS t WHERE t.parent_change_id = $iTicketID";
*/
			default:
				$sQuery = "SELECT UserRequest AS t WHERE t.parent_request_id = $iTicketID";

		}

		// retrieve Tickets from DB
		\IssueLog::Info("getChildTickets() about to retrieve children…", AIBaseHelper::MODULE_CODE);
		$oResultSet = new \DBObjectSet (\DBObjectSearch::FromOQL($sQuery));
		if ($oResultSet->Count() > 0 ){
			while ($oChildTicket = $oResultSet->Fetch()) {
				array_push($aChildTicketList, $this->getTicketData($oChildTicket));		
			}
		}
		return $aChildTicketList;
	}

	/**
	 * Check if an AI result is within valid parameters (guardrail against (some) hallucinations)
	 * @param $aValidresults array of valid results (e.g. ServiceSubcategories). 
	 * @param $sKey $aValidResults[$sKkey] will be checked against $sValue
	 * @param $sValue the value that AI provided, to be determined if valid or not
	 */
	public function isValidResult($aValidResults,$sKey,$sValue) {
		if ($aValidResults[$key] == $sValue) return true;
		return false;

	}




    /**
     * Sets the type of the given ticket based on AI analysis.
     *
     * @param \Combodo\itop\model\dbmodel\PersistentObject $oTicket The ticket to set the type for.
     * @return bool Returns true if the type was successfully set, false otherwise.
     */
    public function setType($oTicket) {
        \IssueLog::Info("setType() about to guess if Incident or Service Request", AIBaseHelper::MODULE_CODE);
		$aType = $this->oAIEngine->determineType($oTicket);
		if (($aType['type'] == "incident") || ($aType['type'] == "service_request")) {
		  $oTicket->Set("request_type", $aType['type']);
		  \IssueLog::Info("setType() thinks it's a...". $aType['type'], AIBaseHelper::MODULE_CODE);
		  $sLabel = Dict::S('Ticket:ItomigAIAction:AISetTicketType:update');
		  $sResult = sprintf($sLabel, $aType['type'], $aType['rationale']);
		  return $sResult;
		}
		\IssueLog::Info("setType() failing", AIBaseHelper::MODULE_CODE);
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


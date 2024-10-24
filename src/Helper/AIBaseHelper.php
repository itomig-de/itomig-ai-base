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

class AIBaseHelper
{
	public const MODULE_CODE = 'itomig-ai-base';

/**
	 * Retrieve Service Catalogue for a customer, one line per Subcategory
	 * @param int $iTicketOrgID org_id of the customer
	 * @param bool $bReturnArray
	 * @return array|string
	 * @throws \CoreException
	 */
	public function getServiceCatalogue($iTicketOrgID, $bReturnArray = false) {

		$sTextualSerCat = "";

		// get whole SerCat incl. Service Family, Service, Service Subcategory
		$sQuery = "SELECT ServiceSubcategory AS sc JOIN Service AS s ON sc.service_id=s.id 
            JOIN lnkCustomerContractToService AS l1 ON l1.service_id=s.id 
            JOIN CustomerContract AS cc ON l1.customercontract_id=cc.id 
            WHERE cc.org_id = $iTicketOrgID AND s.status != 'obsolete'";

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
	 * Retrieve detailed information about a ticket.
	 * @param $oTicket the Ticket object
	 * @return array with attribute name => value
	 */
	public function getTicketData($oTicket)
	{
		// TODO Ticket must have a public_log, which de facto makes this incompatible with anything but UserRequests
		$sPublicLog = $oTicket->Get('public_log');
		$sTitle = $oTicket->Get('title');
		$sRef = $oTicket->Get('ref');
		$sDescription = $oTicket->Get('description');
		$sCaller = $oTicket->Get('caller_id_friendlyname');
		$sOrg = $oTicket->Get('org_id_friendlyname');
		$sStatus = $oTicket->Get('status');

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
}


<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
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

use IssueLog;
use Itomig\iTop\Extension\AIBase\Contracts\iAIToolProvider;
use LLPhant\Chat\FunctionInfo\FunctionInfo;
use MetaModel;
use UserRights;

/**
 * Context-free AI system tools that are always available, regardless of object context.
 *
 * These tools do not require a DBObject context and should be available in every
 * AI conversation, including those without an object.
 */
class AISystemTools implements iAIToolProvider
{
	/**
	 * Get the current date and time.
	 *
	 * @return string Current date and time in ISO 8601 format.
	 */
	public function getCurrentDateTime(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called (no context needed)", AIBaseHelper::MODULE_CODE);
		$result = date('Y-m-d H:i:s');
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
	}

	/**
	 * Get information about the currently logged-in user.
	 *
	 * @return string JSON with user info (id, login, language, contact details).
	 */
	public function getCurrentUser(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called (no context needed)", AIBaseHelper::MODULE_CODE);
		$oUser = UserRights::GetUserObject();
		if ($oUser === null) {
			return json_encode(['error' => 'No user logged in']);
		}

		$aResult = [
			'user' => [
				'id' => (string) $oUser->GetKey(),
				'login' => $oUser->Get('login'),
				'language' => $oUser->Get('language'),
				'contact' => null,
			],
		];

		$iContactId = (int) $oUser->Get('contactid');
		if ($iContactId > 0) {
			$oContact = MetaModel::GetObject('Contact', $iContactId, false);
			if ($oContact !== null) {
				$aContact = [
					'id' => (string) $oContact->GetKey(),
					'class' => get_class($oContact),
					'friendlyname' => $oContact->GetName(),
					'name' => $oContact->Get('name'),
					'email' => $oContact->Get('email'),
					'org_id' => (string) $oContact->Get('org_id'),
					'org_name' => $oContact->Get('org_name'),
				];

				// Person hat zusätzlich first_name
				if ($oContact instanceof \Person) {
					$aContact['first_name'] = $oContact->Get('first_name');
				}

				$aResult['user']['contact'] = $aContact;
			}
		}

		IssueLog::Debug(__METHOD__ . ": Returning user info for " . $oUser->Get('login'), AIBaseHelper::MODULE_CODE);
		return json_encode($aResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Get the permission profiles (roles) assigned to the currently logged-in user.
	 *
	 * @return string JSON with user_id, login, and array of profiles with name and description.
	 */
	public function getCurrentUserProfiles(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called (no context needed)", AIBaseHelper::MODULE_CODE);
		$oUser = UserRights::GetUserObject();
		if ($oUser === null) {
			return json_encode(['error' => 'No user logged in']);
		}

		$aProfiles = UserRights::ListProfiles();
		$aProfileDetails = [];

		foreach ($aProfiles as $iProfileId => $sProfileName) {
			$aProfileInfo = [
				'name' => $sProfileName,
				'description' => '',
			];
			$oProfile = MetaModel::GetObject('URP_Profiles', $iProfileId, false);
			if ($oProfile !== null) {
				$aProfileInfo['description'] = $oProfile->Get('description');
			}
			$aProfileDetails[] = $aProfileInfo;
		}

		$aResult = [
			'user_id' => (string) $oUser->GetKey(),
			'login' => $oUser->Get('login'),
			'profiles' => $aProfileDetails,
		];

		IssueLog::Debug(__METHOD__ . ": Returning " . count($aProfileDetails) . " profiles for " . $oUser->Get('login'), AIBaseHelper::MODULE_CODE);
		return json_encode($aResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Returns an array of FunctionInfo objects for the context-free system tools.
	 *
	 * @return FunctionInfo[] Array of FunctionInfo objects ready for use with LLPhant.
	 */
	public function getAITools(): array
	{
		return [
			new FunctionInfo(
				'getCurrentDateTime',
				$this,
				'Get current server date and time. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getCurrentUser',
				$this,
				'Get information about the currently logged-in user: user ID, login, language, and linked contact/person details (name, email, organization). No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getCurrentUserProfiles',
				$this,
				'Get the permission profiles (roles) assigned to the currently logged-in user. Each profile has a name and description explaining what permissions it grants. No parameters required.',
				[],
				[]
			),
		];
	}
}

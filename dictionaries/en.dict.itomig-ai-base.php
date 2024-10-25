<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
 * @author DavidM. Gümbel <david.guembel@itomig.de>
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

Dict::Add('EN US', 'English', 'English', array(

	'UI:AIResponse:GenericAI:Prompt:GetCompletions' => 'Response (default)',
	'UI:AIResponse:GenericAI:Prompt:Translate' => 'Translate',
	'UI:AIResponse:GenericAI:Prompt:improveText' => 'Improve text',
	'UI:AIResponse:GenericAI:Prompt:summarizeTicket' => 'Summarize ticket',
	'UI:AIResponse:GenericAI:Prompt:recategorizeTicket' => 'Propose new or better categorization',

	'GenericAIEngine:autoRecategorizeTicket:success' => 'Successfully updated categorization and request type. Rationale: %1$s',
	'GenericAIEngine:autoRecategorizeTicket:failure' => 'Failure. AI chose ID: %1$s, but the Service Catalogue does not contain it. Please optimize your Catalogue and / or your prompt',

	'Ticket:ItomigAIAction:AISetTicketType:update' => 'Successfully updated request type to: %1$s. Rationale: %2$s',



));

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

Dict::Add('FR FR', 'French', 'Français', array(
    'UI:AIResponse:GenericAI:Prompt:GetCompletions' => 'Response (default)',
    'UI:AIResponse:GenericAI:Prompt:Translate' => 'Traduire',
    'UI:AIResponse:GenericAI:Prompt:improveText' => 'Améliorer le texte',
    'UI:AIResponse:GenericAI:Prompt:summarizeTicket' => 'Résumer le ticket',
    'UI:AIResponse:GenericAI:Prompt:recategorizeTicket' => 'Proposer une nouvelle ou meilleure catégorisation',
    'GenericAIEngine:autoRecategorizeTicket:success' => 'Mise à jour réussie de la catégorisation et du type de demande. Rationale : %1$s',
    'GenericAIEngine:autoRecategorizeTicket:failure' => 'Échec. AI a choisi l\'ID : %1$s, mais le catalogue de services ne le contient pas. Veuillez optimiser votre catalogue et / ou votre invite',
    'Ticket:ItomigAIAction:AISetTicketType:update' => 'Successfully updated request type to : %1$s. Rationale : %2$s',


)) ;
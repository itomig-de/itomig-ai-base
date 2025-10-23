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

Dict::Add('DE DE', 'Deutsch', 'Deutsch', array(
    'Menu:AIBaseDiagnostics' => 'AI-Diagnose',
    'itomig-ai-base/Operation:show/Title' => 'AI-Diagnose',

    // Page and sections
    'Diagnostics:PageTitle' => 'AI-Diagnose',
    'Diagnostics:ConfigPanel:Title' => 'Konfigurationsstatus',
    'Diagnostics:ConfigPanel:EngineName' => 'AI-Engine-Name',
    'Diagnostics:ConfigPanel:EngineConfig' => 'Engine-Konfiguration',
    'Diagnostics:ConfigPanel:NotFound' => 'Nicht in der Konfiguration gefunden',

    // Test panel
    'Diagnostics:TestPanel:Title' => 'AI-Verbindung testen',
    'Diagnostics:TestPanel:MessageLabel' => 'Testnachricht',
    'Diagnostics:TestPanel:MessagePlaceholder' => 'Geben Sie hier Ihre Testnachricht ein...',
    'Diagnostics:TestPanel:VerboseLabel' => 'Detaillierte Diagnoseinformationen anzeigen',
    'Diagnostics:TestPanel:SubmitButton' => 'An AI-Engine senden',
    'Diagnostics:TestPanel:SystemPromptLabel' => 'System-Prompt',
    'Diagnostics:TestPanel:SystemPromptSelect' => 'System-Prompt auswählen (optional)',
    'Diagnostics:TestPanel:SystemPromptNone' => '(Keine - Standard verwenden)',

    // Results
    'Diagnostics:ResultPanel:Title' => 'AI-Antwort',
    'Diagnostics:ErrorPanel:Title' => 'Fehler',

    // Verbose diagnostics
    'Diagnostics:VerbosePanel:Title' => 'Diagnosedetails',
    'Diagnostics:VerbosePanel:RequestInfo' => 'Anfrageinformationen',
    'Diagnostics:VerbosePanel:Message' => 'Nachricht',
    'Diagnostics:VerbosePanel:SystemInstruction' => 'Systemanweisung',
    'Diagnostics:VerbosePanel:MessageLength' => 'Nachrichtenlänge',
    'Diagnostics:VerbosePanel:InstructionLength' => 'Anweisungslänge',
    'Diagnostics:VerbosePanel:Timing' => 'Zeitinformationen',
    'Diagnostics:VerbosePanel:Duration' => 'Gesamtdauer',
    'Diagnostics:VerbosePanel:StartTime' => 'Startzeit',
    'Diagnostics:VerbosePanel:EndTime' => 'Endzeit',
    'Diagnostics:VerbosePanel:EngineConfig' => 'Engine-Konfiguration',

    // Prompts panel
    'Diagnostics:PromptsPanel:Title' => 'Aktuelle System-Prompts',
    'Diagnostics:PromptsPanel:BuiltIn' => 'Eingebaut',
    'Diagnostics:PromptsPanel:Custom' => 'Benutzerdefiniert',
    'Diagnostics:PromptsPanel:Overridden' => 'Überschrieben',
    'Diagnostics:PromptsPanel:NoCustom' => 'Keine benutzerdefinierten Prompts konfiguriert',

    // Editor panel
    'Diagnostics:EditorPanel:Title' => 'Konfigurationscode generieren',
    'Diagnostics:EditorPanel:SelectPrompt' => 'Prompt zum Bearbeiten auswählen',
    'Diagnostics:EditorPanel:SelectPlaceholder' => '-- Wählen Sie einen Prompt --',
    'Diagnostics:EditorPanel:NewPrompt' => 'Oder neuen Prompt erstellen',
    'Diagnostics:EditorPanel:PromptKeyLabel' => 'Prompt-Schlüssel',
    'Diagnostics:EditorPanel:PromptKeyPlaceholder' => 'z.B. meinBenutzerdefinierterPrompt',
    'Diagnostics:EditorPanel:PromptValueLabel' => 'Prompt-Text',
    'Diagnostics:EditorPanel:PromptValuePlaceholder' => 'Geben Sie die System-Prompt-Anweisungen ein...',
    'Diagnostics:EditorPanel:GenerateButton' => 'Konfigurationscode generieren',
    'Diagnostics:EditorPanel:Warning' => 'Hinweis: Dieses Tool generiert PHP-Code, den Sie manuell zu Ihrer Konfigurationsdatei hinzufügen müssen. Konfigurationsdateien sind in der Produktion aus Sicherheitsgründen schreibgeschützt.',

    // Save results
    'Diagnostics:SaveError' => 'Validierungsfehler',
    'Diagnostics:SaveFallback:Title' => 'Konfigurationscode generiert',
    'Diagnostics:SaveFallback:Message' => 'Fügen Sie diesen Code zu Ihrer Konfigurationsdatei unter conf/production/config-itop.php hinzu:',
));

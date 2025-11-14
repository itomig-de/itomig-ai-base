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
    'Menu:AIBaseDiagnostics' => 'AI Diagnostics',
    'itomig-ai-base/Operation:Show/Title' => 'AI Diagnostics',
    'itomig-ai-base/Operation:show/Title' => 'AI Diagnostics',

    // Page and sections
    'Diagnostics:PageTitle' => 'AI Diagnostics',
    'Diagnostics:ConfigPanel:Title' => 'Configuration Status',
    'Diagnostics:ConfigPanel:EngineName' => 'AI Engine Name',
    'Diagnostics:ConfigPanel:EngineConfig' => 'Engine Configuration',
    'Diagnostics:ConfigPanel:NotFound' => 'Not found in configuration',

    // Test panel
    'Diagnostics:TestPanel:Title' => 'Test AI Connection',
    'Diagnostics:TestPanel:MessageLabel' => 'Test Message',
    'Diagnostics:TestPanel:MessagePlaceholder' => 'Enter your test message here...',
    'Diagnostics:TestPanel:VerboseLabel' => 'Show detailed diagnostic information',
    'Diagnostics:TestPanel:SubmitButton' => 'Send to AI Engine',
    'Diagnostics:TestPanel:SystemPromptLabel' => 'System Prompt',
    'Diagnostics:TestPanel:SystemPromptSelect' => 'Select a system prompt (optional)',
    'Diagnostics:TestPanel:SystemPromptNone' => '(None - use default)',

    // Results
    'Diagnostics:ResultPanel:Title' => 'AI Response',
    'Diagnostics:ErrorPanel:Title' => 'Error',

    // Verbose diagnostics
    'Diagnostics:VerbosePanel:Title' => 'Diagnostic Details',
    'Diagnostics:VerbosePanel:RequestInfo' => 'Request Information',
    'Diagnostics:VerbosePanel:Message' => 'Message',
    'Diagnostics:VerbosePanel:SystemInstruction' => 'System Instruction',
    'Diagnostics:VerbosePanel:MessageLength' => 'Message Length',
    'Diagnostics:VerbosePanel:InstructionLength' => 'Instruction Length',
    'Diagnostics:VerbosePanel:Timing' => 'Timing Information',
    'Diagnostics:VerbosePanel:Duration' => 'Total Duration',
    'Diagnostics:VerbosePanel:StartTime' => 'Start Time',
    'Diagnostics:VerbosePanel:EndTime' => 'End Time',
    'Diagnostics:VerbosePanel:EngineConfig' => 'Engine Configuration',

    // Prompts panel
    'Diagnostics:PromptsPanel:Title' => 'Current System Prompts',
    'Diagnostics:PromptsPanel:BuiltIn' => 'Built-in',
    'Diagnostics:PromptsPanel:Custom' => 'Custom',
    'Diagnostics:PromptsPanel:Overridden' => 'Overridden',
    'Diagnostics:PromptsPanel:NoCustom' => 'No custom prompts configured',

    // Editor panel
    'Diagnostics:EditorPanel:Title' => 'Generate Configuration Code',
    'Diagnostics:EditorPanel:SelectPrompt' => 'Select Prompt to Edit',
    'Diagnostics:EditorPanel:SelectPlaceholder' => '-- Choose a prompt --',
    'Diagnostics:EditorPanel:NewPrompt' => 'Or create new prompt',
    'Diagnostics:EditorPanel:PromptKeyLabel' => 'Prompt Key',
    'Diagnostics:EditorPanel:PromptKeyPlaceholder' => 'e.g., myCustomPrompt',
    'Diagnostics:EditorPanel:PromptValueLabel' => 'Prompt Text',
    'Diagnostics:EditorPanel:PromptValuePlaceholder' => 'Enter the system prompt instructions...',
    'Diagnostics:EditorPanel:GenerateButton' => 'Generate Configuration Code',
    'Diagnostics:EditorPanel:Warning' => 'Note: This tool generates PHP code for you to manually add to your configuration file. Configuration files are read-only in production for security.',

    // Save results
    'Diagnostics:SaveError' => 'Validation Error',
    'Diagnostics:SaveFallback:Title' => 'Configuration Code Generated',
    'Diagnostics:SaveFallback:Message' => 'Add this code to your configuration file at conf/production/config-itop.php:',
));

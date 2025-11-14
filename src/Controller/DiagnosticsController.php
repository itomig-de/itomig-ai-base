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

namespace Itomig\iTop\Extension\AIBase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Combodo\iTop\Application\UI\Base\Component\Alert\AlertUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Panel\PanelUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Html\Html;
use Itomig\iTop\Extension\AIBase\Service\AIService;
use Itomig\iTop\Extension\AIBase\Service\ConfigurationService;
use Itomig\iTop\Extension\AIBase\Helper\DiagnosticsHelper;
use MetaModel;
use Exception;
use Utils;
use Dict;

class DiagnosticsController extends Controller
{
    /**
     * Main page - displays all diagnostic sections
     */
    public function OperationShow()
    {
        $aParams = [];

        // Section 1: Configuration Status Panel
        $aParams['oConfigPanel'] = $this->BuildConfigPanel();

        // Section 2: Test Panel
        $aParams['oTestPanel'] = $this->BuildTestPanel();

        // Section 3: View Prompts Panel
        $oConfigService = new ConfigurationService();
        $aPrompts = $oConfigService->GetSystemPrompts();
        $aParams['oPromptsPanel'] = DiagnosticsHelper::BuildPromptsPanel($aPrompts);

        // Section 4: Editor Panel
        $aParams['oEditorPanel'] = $this->BuildEditorPanel($aPrompts);

        try {
            $this->DisplayPage($aParams);
        } catch (\Twig\Error\LoaderError $e) {
            \IssueLog::Error("Caught Twig LoaderError: " . $e->getMessage());
        }
    }

    /**
     * Test operation - handles test message submission with verbose mode and system prompt selection
     */
    public function OperationTest()
    {
        $sMessage = utils::ReadParam('message', '', false, 'raw_data');
        $bVerbose = utils::ReadParam('verbose', false);
        $sSystemPromptKey = utils::ReadParam('system_prompt', '');

        $sResult = '';
        $sError = '';
        $aVerboseInfo = null;

        if (!empty($sMessage)) {
            try {
                $fStartTime = microtime(true);

                $oAIService = new AIService();

                // Use selected system prompt or default
                if (!empty($sSystemPromptKey)) {
                    $sResult = $oAIService->PerformSystemInstruction($sMessage, $sSystemPromptKey);
                    // Get the actual system instruction text for verbose output
                    $sSystemInstruction = $oAIService->aSystemInstructions[$sSystemPromptKey] ?? '';
                } else {
                    $sResult = $oAIService->GetCompletion($sMessage);
                    $sSystemInstruction = '';
                }

                $fEndTime = microtime(true);

                // Build verbose diagnostics if requested
                if ($bVerbose) {
                    $aEngineConfig = MetaModel::GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', []);
                    $aVerboseInfo = DiagnosticsHelper::BuildVerboseInfo(
                        $sMessage,
                        $sSystemInstruction,
                        $fStartTime,
                        $fEndTime,
                        $aEngineConfig
                    );
                }

            } catch (Exception $e) {
                $sError = "An exception occurred:\n\n" . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString();
            }
        }

        // Build page with all sections
        $aParams = [];

        // Config panel
        $aParams['oConfigPanel'] = $this->BuildConfigPanel();

        // Test panel (with previous message pre-filled)
        $aParams['oTestPanel'] = $this->BuildTestPanel($sMessage, $bVerbose, $sSystemPromptKey);

        // Results
        if (!empty($sResult)) {
            $oResultPanel = PanelUIBlockFactory::MakeForSuccess(Dict::S('Diagnostics:ResultPanel:Title'));
            $oResultPanel->SetIsCollapsible(false);
            $oResultPanel->AddSubBlock(new Html('<pre>' . htmlentities($sResult) . '</pre>'));
            $aParams['oResultPanel'] = $oResultPanel;
        }

        // Error display
        if (!empty($sError)) {
            $oErrorPanel = PanelUIBlockFactory::MakeForDanger(Dict::S('Diagnostics:ErrorPanel:Title'));
            $oErrorPanel->AddSubBlock(new Html('<pre>' . htmlentities($sError) . '</pre>'));
            $aParams['oErrorPanel'] = $oErrorPanel;
        }

        // Verbose diagnostics
        if ($aVerboseInfo !== null) {
            $aParams['oVerbosePanel'] = DiagnosticsHelper::BuildVerbosePanel($aVerboseInfo);
        }

        // Prompts panel
        $oConfigService = new ConfigurationService();
        $aPrompts = $oConfigService->GetSystemPrompts();
        $aParams['oPromptsPanel'] = DiagnosticsHelper::BuildPromptsPanel($aPrompts);

        // Editor panel
        $aParams['oEditorPanel'] = $this->BuildEditorPanel($aPrompts);

        try {
            $this->DisplayPage($aParams, 'show');
        } catch (\Twig\Error\LoaderError $e) {
            \IssueLog::Error("Caught Twig LoaderError: " . $e->getMessage());
        }
    }

    /**
     * AJAX endpoint to load a specific prompt for editing
     */
    public function OperationLoadPrompt()
    {
        $sKey = utils::ReadParam('prompt_key', '');

        if (empty($sKey)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No prompt key specified']);
            exit;
        }

        $oConfigService = new ConfigurationService();
        $aPrompts = $oConfigService->GetSystemPrompts();

        if (isset($aPrompts[$sKey])) {
            header('Content-Type: application/json');
            echo json_encode([
                'key' => $sKey,
                'value' => $aPrompts[$sKey]['text'],
                'is_builtin' => $aPrompts[$sKey]['is_builtin'],
                'is_custom' => $aPrompts[$sKey]['is_custom'],
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Prompt not found']);
        }
        exit;
    }

    /**
     * Save prompt operation - generates PHP code snippet for manual configuration update
     */
    public function OperationSavePrompt()
    {
        $sPromptKey = utils::ReadParam('prompt_key', '');
        $sPromptValue = utils::ReadParam('prompt_value', '', false, 'raw_data');

        $aParams = [];

        // Validate input
        if (empty($sPromptKey) || empty($sPromptValue)) {
            $oAlert = AlertUIBlockFactory::MakeForWarning(
                Dict::S('Diagnostics:SaveError'),
                'Prompt key and value cannot be empty'
            );
            $aParams['oSaveAlert'] = $oAlert;
        } else {
            // Generate PHP code snippet for manual update
            $oConfigService = new ConfigurationService();
            $sPhpCode = $oConfigService->GenerateConfigSnippet($sPromptKey, $sPromptValue);

            // Display the code snippet
            $oAlert = AlertUIBlockFactory::MakeForInformation(
                Dict::S('Diagnostics:SaveFallback:Title'),
                Dict::S('Diagnostics:SaveFallback:Message')
            );
            $oAlert->SetIsCollapsible(false);

            $oAlert->AddSubBlock(new Html(
                '<pre style="
                    margin-top: 1em;
                    padding: 1em;
                    background-color: #f5f5f5;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    font-family: monospace;
                    font-size: 0.9em;
                    overflow-x: auto;
                ">' . htmlentities($sPhpCode) . '</pre>'
            ));

            $aParams['oSaveAlert'] = $oAlert;
        }

        // Rebuild all panels
        $aParams['oConfigPanel'] = $this->BuildConfigPanel();
        $aParams['oTestPanel'] = $this->BuildTestPanel();

        $oConfigService = new ConfigurationService();
        $aPrompts = $oConfigService->GetSystemPrompts();
        $aParams['oPromptsPanel'] = DiagnosticsHelper::BuildPromptsPanel($aPrompts);
        $aParams['oEditorPanel'] = $this->BuildEditorPanel($aPrompts);

        try {
            $this->DisplayPage($aParams, 'show');
        } catch (\Twig\Error\LoaderError $e) {
            \IssueLog::Error("Caught Twig LoaderError: " . $e->getMessage());
        }
    }

    /**
     * Build the configuration status panel
     *
     * @return mixed Panel UIBlock
     */
    protected function BuildConfigPanel()
    {
        $oPanel = PanelUIBlockFactory::MakeNeutral(Dict::S('Diagnostics:ConfigPanel:Title'));
        $oPanel->SetIsCollapsible(true);

        $sEngineName = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.name', null);
        $aEngineConfig = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', null);

        $sHtml = '<dl>';

        // Engine Name
        $sHtml .= '<dt><strong>' . Dict::S('Diagnostics:ConfigPanel:EngineName') . ':</strong></dt>';
        if ($sEngineName) {
            $sHtml .= '<dd>' . htmlentities($sEngineName) . '</dd>';
        } else {
            $sHtml .= '<dd><em>' . Dict::S('Diagnostics:ConfigPanel:NotFound') . '</em></dd>';
        }

        // Engine Configuration
        $sHtml .= '<dt><strong>' . Dict::S('Diagnostics:ConfigPanel:EngineConfig') . ':</strong></dt>';
        if ($aEngineConfig && is_array($aEngineConfig)) {
            $aObfuscated = DiagnosticsHelper::ObfuscateConfig($aEngineConfig);
            $sHtml .= '<dd><pre>' . htmlentities(print_r($aObfuscated, true)) . '</pre></dd>';
        } else {
            $sHtml .= '<dd><em>' . Dict::S('Diagnostics:ConfigPanel:NotFound') . '</em></dd>';
        }

        $sHtml .= '</dl>';

        $oPanel->AddSubBlock(new Html($sHtml));

        return $oPanel;
    }

    /**
     * Build the test panel with form, verbose checkbox, and system prompt selector
     *
     * @param string $sCurrentMessage Previous message to pre-fill
     * @param bool $bVerbose Verbose checkbox state
     * @param string $sSelectedPrompt Selected system prompt
     * @return mixed Panel UIBlock
     */
    protected function BuildTestPanel($sCurrentMessage = '', $bVerbose = false, $sSelectedPrompt = '')
    {
        $oPanel = PanelUIBlockFactory::MakeNeutral(Dict::S('Diagnostics:TestPanel:Title'));

        // Get available prompts for dropdown
        $oConfigService = new ConfigurationService();
        $aPrompts = $oConfigService->GetSystemPrompts();

        $sFormAction = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=itomig-ai-base&exec_page=index.php&operation=Test';

        $sFormHtml = '<form method="POST" action="' . $sFormAction . '">';

        // System prompt selector
        $sFormHtml .= '<div style="margin-bottom: 1em;">';
        $sFormHtml .= '<label for="system_prompt"><strong>' . Dict::S('Diagnostics:TestPanel:SystemPromptLabel') . ':</strong></label><br>';
        $sFormHtml .= '<select name="system_prompt" id="system_prompt" style="width: 100%; padding: 0.5em; margin-top: 0.5em;">';
        $sFormHtml .= '<option value="">' . Dict::S('Diagnostics:TestPanel:SystemPromptNone') . '</option>';

        foreach ($aPrompts as $sKey => $aPromptInfo) {
            $sSelected = ($sKey === $sSelectedPrompt) ? ' selected' : '';
            $sLabel = $sKey;
            if ($aPromptInfo['is_custom'] && $aPromptInfo['is_builtin']) {
                $sLabel .= ' [' . Dict::S('Diagnostics:PromptsPanel:Overridden') . ']';
            } elseif (!$aPromptInfo['is_builtin']) {
                $sLabel .= ' [' . Dict::S('Diagnostics:PromptsPanel:Custom') . ']';
            }
            $sFormHtml .= '<option value="' . htmlentities($sKey) . '"' . $sSelected . '>' . htmlentities($sLabel) . '</option>';
        }

        $sFormHtml .= '</select>';
        $sFormHtml .= '</div>';

        // Message textarea
        $sFormHtml .= '<div style="margin-bottom: 1em;">';
        $sFormHtml .= '<label for="message"><strong>' . Dict::S('Diagnostics:TestPanel:MessageLabel') . ':</strong></label><br>';
        $sFormHtml .= '<textarea name="message" id="message" rows="5" style="width: 100%; padding: 0.5em; margin-top: 0.5em;" placeholder="' . Dict::S('Diagnostics:TestPanel:MessagePlaceholder') . '">';
        $sFormHtml .= htmlentities($sCurrentMessage);
        $sFormHtml .= '</textarea>';
        $sFormHtml .= '</div>';

        // Verbose checkbox
        $sChecked = $bVerbose ? ' checked' : '';
        $sFormHtml .= '<div style="margin-bottom: 1em;">';
        $sFormHtml .= '<label>';
        $sFormHtml .= '<input type="checkbox" name="verbose" value="1"' . $sChecked . '> ';
        $sFormHtml .= Dict::S('Diagnostics:TestPanel:VerboseLabel');
        $sFormHtml .= '</label>';
        $sFormHtml .= '</div>';

        // Submit button
        $sFormHtml .= '<div>';
        $sFormHtml .= '<button type="submit" class="ibo-btn ibo-btn-primary">' . Dict::S('Diagnostics:TestPanel:SubmitButton') . '</button>';
        $sFormHtml .= '</div>';

        $sFormHtml .= '</form>';

        $oPanel->AddSubBlock(new Html($sFormHtml));

        return $oPanel;
    }

    /**
     * Build the prompt editor panel
     *
     * @param array $aPrompts Available prompts
     * @return mixed Panel UIBlock
     */
    protected function BuildEditorPanel(array $aPrompts)
    {
        $oPanel = PanelUIBlockFactory::MakeNeutral(Dict::S('Diagnostics:EditorPanel:Title'));
        $oPanel->SetIsCollapsible(true);

        // Warning message
        $oWarning = AlertUIBlockFactory::MakeForWarning('', Dict::S('Diagnostics:EditorPanel:Warning'));
        $oWarning->SetIsCollapsible(false);
        $oPanel->AddSubBlock($oWarning);

        $sFormAction = utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=itomig-ai-base&exec_page=index.php&operation=SavePrompt';

        $sFormHtml = '<form method="POST" action="' . $sFormAction . '" id="promptEditorForm">';

        // Prompt selector dropdown
        $sFormHtml .= '<div style="margin-bottom: 1.5em;">';
        $sFormHtml .= '<label for="prompt_selector"><strong>' . Dict::S('Diagnostics:EditorPanel:SelectPrompt') . ':</strong></label><br>';
        $sFormHtml .= '<select id="prompt_selector" onchange="loadPromptForEdit(this.value)" style="width: 100%; padding: 0.5em; margin-top: 0.5em;">';
        $sFormHtml .= '<option value="">' . Dict::S('Diagnostics:EditorPanel:SelectPlaceholder') . '</option>';

        foreach ($aPrompts as $sKey => $aPromptInfo) {
            $sLabel = $sKey;
            if ($aPromptInfo['is_custom'] && $aPromptInfo['is_builtin']) {
                $sLabel .= ' [' . Dict::S('Diagnostics:PromptsPanel:Overridden') . ']';
            } elseif (!$aPromptInfo['is_builtin']) {
                $sLabel .= ' [' . Dict::S('Diagnostics:PromptsPanel:Custom') . ']';
            }
            $sFormHtml .= '<option value="' . htmlentities($sKey) . '">' . htmlentities($sLabel) . '</option>';
        }

        $sFormHtml .= '</select>';
        $sFormHtml .= '</div>';

        // New prompt option
        $sFormHtml .= '<div style="margin-bottom: 1.5em;">';
        $sFormHtml .= '<p><strong>' . Dict::S('Diagnostics:EditorPanel:NewPrompt') . '</strong></p>';
        $sFormHtml .= '<label for="prompt_key_input">' . Dict::S('Diagnostics:EditorPanel:PromptKeyLabel') . ':</label><br>';
        $sFormHtml .= '<input type="text" id="prompt_key_input" style="width: 100%; padding: 0.5em; margin-top: 0.5em;" placeholder="' . Dict::S('Diagnostics:EditorPanel:PromptKeyPlaceholder') . '">';
        $sFormHtml .= '</div>';

        // Hidden field for the actual key being saved
        $sFormHtml .= '<input type="hidden" name="prompt_key" id="prompt_key_hidden" value="">';

        // Prompt value textarea
        $sFormHtml .= '<div style="margin-bottom: 1.5em;">';
        $sFormHtml .= '<label for="prompt_editor"><strong>' . Dict::S('Diagnostics:EditorPanel:PromptValueLabel') . ':</strong></label><br>';
        $sFormHtml .= '<textarea name="prompt_value" id="prompt_editor" rows="10" style="width: 100%; padding: 0.5em; margin-top: 0.5em; font-family: monospace;" placeholder="' . Dict::S('Diagnostics:EditorPanel:PromptValuePlaceholder') . '"></textarea>';
        $sFormHtml .= '</div>';

        // Generate button
        $sFormHtml .= '<div>';
        $sFormHtml .= '<button type="submit" class="ibo-btn ibo-btn-primary">' . Dict::S('Diagnostics:EditorPanel:GenerateButton') . '</button>';
        $sFormHtml .= '</div>';

        $sFormHtml .= '</form>';

        // JavaScript for loading prompts
        $sFormHtml .= '<script>
function loadPromptForEdit(promptKey) {
    if (!promptKey) {
        document.getElementById("prompt_editor").value = "";
        document.getElementById("prompt_key_hidden").value = "";
        document.getElementById("prompt_key_input").value = "";
        return;
    }

    var url = "' . utils::GetAbsoluteUrlAppRoot() . 'pages/exec.php?exec_module=itomig-ai-base&exec_page=index.php&operation=LoadPrompt&prompt_key=" + encodeURIComponent(promptKey);

    fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.error) {
                alert("Error: " + data.error);
                return;
            }
            document.getElementById("prompt_editor").value = data.value || "";
            document.getElementById("prompt_key_hidden").value = data.key || "";
            document.getElementById("prompt_key_input").value = "";
        })
        .catch(function(error) {
            console.error("Failed to load prompt:", error);
            alert("Failed to load prompt. Check console for details.");
        });
}

// On form submit, ensure we have a key set
document.getElementById("promptEditorForm").addEventListener("submit", function(e) {
    var hiddenKey = document.getElementById("prompt_key_hidden").value;
    var newKey = document.getElementById("prompt_key_input").value.trim();

    if (newKey) {
        // Creating new prompt
        document.getElementById("prompt_key_hidden").value = newKey;
    } else if (!hiddenKey) {
        e.preventDefault();
        alert("Please select an existing prompt or enter a new prompt key.");
        return false;
    }
});
</script>';

        $oPanel->AddSubBlock(new Html($sFormHtml));

        return $oPanel;
    }
}

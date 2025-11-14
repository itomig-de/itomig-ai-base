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

namespace Itomig\iTop\Extension\AIBase\Helper;

use Combodo\iTop\Application\UI\Base\Component\Alert\AlertUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Panel\PanelUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Html\Html;
use Combodo\iTop\Application\UI\Base\Component\Field\FieldUIBlockFactory;
use Combodo\iTop\Application\UI\Base\Component\Input\InputUIBlockFactory;
use Dict;

class DiagnosticsHelper
{
    public const MODULE_NAME = 'itomig-ai-base';

 /**
 * Gibt den Pfad zum Template-Verzeichnis des Moduls zurück.
 * @return string
 **/
    static public function GetTemplatePath()
    {
      return MODULESROOT ."/". self::MODULE_NAME . '/templates';
    }


    /**
     * GetModuleRoute.
     *
     * @return string
     * @throws Exception
     */
    static public function GetModuleRoute()
    {
        return \Utils::GetAbsoluteUrlModulesRoot() . '/' . DiagnosticsHelper::MODULE_NAME;
    }

    /**
     * Build verbose diagnostic information array
     *
     * @param string $sMessage User message
     * @param string $sSystemInstruction System prompt used
     * @param float $fStartTime Start time from microtime(true)
     * @param float $fEndTime End time from microtime(true)
     * @param array $aEngineConfig Engine configuration
     * @return array Structured verbose information
     */
    public static function BuildVerboseInfo(
        string $sMessage,
        string $sSystemInstruction,
        float $fStartTime,
        float $fEndTime,
        array $aEngineConfig
    ): array
    {
        $fDurationMs = ($fEndTime - $fStartTime) * 1000;

        return [
            'request' => [
                'message' => $sMessage,
                'system_instruction' => $sSystemInstruction,
                'message_length' => strlen($sMessage),
                'instruction_length' => strlen($sSystemInstruction),
            ],
            'timing' => [
                'start' => date('Y-m-d H:i:s', (int)$fStartTime) . '.' . sprintf('%03d', ($fStartTime - floor($fStartTime)) * 1000),
                'end' => date('Y-m-d H:i:s', (int)$fEndTime) . '.' . sprintf('%03d', ($fEndTime - floor($fEndTime)) * 1000),
                'duration_ms' => round($fDurationMs, 2),
                'duration_human' => round($fDurationMs / 1000, 2) . ' seconds',
            ],
            'config' => self::ObfuscateConfig($aEngineConfig),
        ];
    }

    /**
     * Obfuscate sensitive data in configuration array
     *
     * @param array $aConfig Configuration array
     * @return array Obfuscated configuration
     */
    public static function ObfuscateConfig(array $aConfig): array
    {
        $aObfuscated = $aConfig;

        // Obfuscate API key
        if (isset($aObfuscated['api_key']) && !empty($aObfuscated['api_key'])) {
            $sApiKey = $aObfuscated['api_key'];
            if (strlen($sApiKey) > 5) {
                $aObfuscated['api_key'] = substr($sApiKey, 0, 5) . '...';
            }
        }

        return $aObfuscated;
    }

    /**
     * Build a Panel UIBlock for displaying verbose diagnostics
     *
     * @param array $aVerboseInfo Verbose information from BuildVerboseInfo()
     * @return mixed Panel UIBlock
     */
    public static function BuildVerbosePanel(array $aVerboseInfo)
    {
        $oPanel = PanelUIBlockFactory::MakeNeutral(Dict::S('Diagnostics:VerbosePanel:Title'));
        $oPanel->SetIsCollapsible(true);

        // Request Information Section
        $sRequestHtml = '<h3>' . Dict::S('Diagnostics:VerbosePanel:RequestInfo') . '</h3>';
        $sRequestHtml .= '<dl>';
        $sRequestHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:Message') . ':</strong></dt>';
        $sRequestHtml .= '<dd><pre>' . htmlentities($aVerboseInfo['request']['message']) . '</pre></dd>';
        $sRequestHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:MessageLength') . ':</strong></dt>';
        $sRequestHtml .= '<dd>' . $aVerboseInfo['request']['message_length'] . ' characters</dd>';
        $sRequestHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:SystemInstruction') . ':</strong></dt>';
        $sRequestHtml .= '<dd><pre>' . htmlentities($aVerboseInfo['request']['system_instruction']) . '</pre></dd>';
        $sRequestHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:InstructionLength') . ':</strong></dt>';
        $sRequestHtml .= '<dd>' . $aVerboseInfo['request']['instruction_length'] . ' characters</dd>';
        $sRequestHtml .= '</dl>';

        $oPanel->AddSubBlock(new Html($sRequestHtml));

        // Timing Information Section
        $sTimingHtml = '<h3>' . Dict::S('Diagnostics:VerbosePanel:Timing') . '</h3>';
        $sTimingHtml .= '<dl>';
        $sTimingHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:StartTime') . ':</strong></dt>';
        $sTimingHtml .= '<dd>' . $aVerboseInfo['timing']['start'] . '</dd>';
        $sTimingHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:EndTime') . ':</strong></dt>';
        $sTimingHtml .= '<dd>' . $aVerboseInfo['timing']['end'] . '</dd>';
        $sTimingHtml .= '<dt><strong>' . Dict::S('Diagnostics:VerbosePanel:Duration') . ':</strong></dt>';
        $sTimingHtml .= '<dd>' . $aVerboseInfo['timing']['duration_human'] . ' (' . $aVerboseInfo['timing']['duration_ms'] . ' ms)</dd>';
        $sTimingHtml .= '</dl>';

        $oPanel->AddSubBlock(new Html($sTimingHtml));

        // Engine Configuration Section
        $sConfigHtml = '<h3>' . Dict::S('Diagnostics:VerbosePanel:EngineConfig') . '</h3>';
        $sConfigHtml .= '<pre>' . htmlentities(print_r($aVerboseInfo['config'], true)) . '</pre>';

        $oPanel->AddSubBlock(new Html($sConfigHtml));

        return $oPanel;
    }

    /**
     * Build a Panel UIBlock for viewing current system prompts
     *
     * @param array $aPrompts Prompts array from ConfigurationService::GetSystemPrompts()
     * @return mixed Panel UIBlock
     */
    public static function BuildPromptsPanel(array $aPrompts)
    {
        $oPanel = PanelUIBlockFactory::MakeNeutral(Dict::S('Diagnostics:PromptsPanel:Title'));
        $oPanel->SetIsCollapsible(true);

        if (empty($aPrompts)) {
            $oPanel->AddSubBlock(new Html('<p>' . Dict::S('Diagnostics:PromptsPanel:NoCustom') . '</p>'));
            return $oPanel;
        }

        $sHtml = '<div class="prompts-list">';

        foreach ($aPrompts as $sKey => $aPromptInfo) {
            $sHtml .= '<div class="prompt-item" style="margin-bottom: 1.5em; padding: 1em; border: 1px solid #ddd; border-radius: 4px;">';
            $sHtml .= '<div style="margin-bottom: 0.5em;"><strong>' . htmlentities($sKey) . '</strong> ';

            // Badge for built-in/custom/overridden
            if ($aPromptInfo['is_builtin'] && !$aPromptInfo['is_custom']) {
                $sHtml .= '<span style="background-color: #e7f3fe; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">' . Dict::S('Diagnostics:PromptsPanel:BuiltIn') . '</span>';
            } elseif ($aPromptInfo['is_builtin'] && $aPromptInfo['is_custom']) {
                $sHtml .= '<span style="background-color: #fff3cd; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">' . Dict::S('Diagnostics:PromptsPanel:Overridden') . '</span>';
            } else {
                $sHtml .= '<span style="background-color: #d1ecf1; padding: 2px 6px; border-radius: 3px; font-size: 0.85em;">' . Dict::S('Diagnostics:PromptsPanel:Custom') . '</span>';
            }

            $sHtml .= '</div>';

            // Show full prompt text in scrollable code block
            $sText = $aPromptInfo['text'];
            $sHtml .= '<pre style="
                margin: 0.5em 0 0 0;
                padding: 0.75em;
                background-color: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-family: monospace;
                font-size: 0.85em;
                line-height: 1.4;
                max-height: 300px;
                overflow-y: auto;
                white-space: pre-wrap;
                word-wrap: break-word;
                color: #333;
            ">' . htmlentities($sText) . '</pre>';

            $sHtml .= '</div>';
        }

        $sHtml .= '</div>';

        $oPanel->AddSubBlock(new Html($sHtml));

        return $oPanel;
    }
}

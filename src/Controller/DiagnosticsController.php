<?php

namespace Itomig\iTop\Extension\AIBase\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use Itomig\iTop\Extension\AIBase\Service\AIService;
use MetaModel;
use Exception;
use utils;

class DiagnosticsController extends Controller
{
    public function OperationShow()
    {
        $sTemplateName = '@itomig-ai-base/Show.html.twig';
        \IssueLog::Info("DiagnosticsController::OperationShow(): using template: " . $sTemplateName);

        $sEngineName = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.name', null);
        $aEngineConfig = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', null);
        $sConfigDisplay = '';

        if ($aEngineConfig && is_array($aEngineConfig)) {
            // Obfuscate the API key for security
            if (isset($aEngineConfig['api_key'])) {
                $aEngineConfig['api_key'] = substr($aEngineConfig['api_key'], 0, 5) . '...';
            }
            $sConfigDisplay = print_r($aEngineConfig, true);
        }

        $this->DisplayPage([
            'sEngineName' => $sEngineName,
            'sConfigDisplay' => $sConfigDisplay,
        ], $sTemplateName);
    }

    public function OperationTest()
    {
        $sTemplateName = '@itomig-ai-base/Show.html.twig';
        \IssueLog::Info("DiagnosticsController::OperationTest(): using template: " . $sTemplateName);
        $sMessage = utils::ReadParam('message', '', false, 'raw_data');
        $sResult = '';
        $sError = '';

        if (!empty($sMessage)) {
            try {
                $oAIService = new AIService();
                $sResult = $oAIService->GetCompletion($sMessage);
            } catch (Exception $e) {
                $sError = "An exception occurred:\n\n" . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString();
            }
        }

        $sEngineName = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.name', null);
        $aEngineConfig = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', null);
        $sConfigDisplay = '';

        if ($aEngineConfig && is_array($aEngineConfig)) {
            // Obfuscate the API key for security
            if (isset($aEngineConfig['api_key'])) {
                $aEngineConfig['api_key'] = substr($aEngineConfig['api_key'], 0, 5) . '...';
            }
            $sConfigDisplay = print_r($aEngineConfig, true);
        }

        $this->DisplayPage([
            'sEngineName' => $sEngineName,
            'sConfigDisplay' => $sConfigDisplay,
            'sResult' => $sResult,
            'sError' => $sError,
        ], $sTemplateName);
    }
}

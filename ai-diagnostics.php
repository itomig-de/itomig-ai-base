<?php
//
// iTop ITOMIG AI Base - Diagnostics Tool
//
use Itomig\iTop\Extension\AIBase\Service\AIService;

// --- Bootstrap iTop Environment and Require Login ---
require_once('../../approot.inc.php');
require_once(APPROOT.'/application/startup.inc.php');
require_once(APPROOT.'/application/application.inc.php');
require_once(APPROOT.'/application/loginwebpage.class.inc.php');
LoginWebPage::DoLogin();

// --- Require Administrator Profile ---
$oUser = UserRights::GetUserObject();
if (!$oUser || !UserRights::HasProfile('Administrator', $oUser))
{
    $oPage = new WebPage('Access Denied');
    $oPage->set_base(utils::GetAbsoluteUrlAppRoot());
    $oPage->add_style('.container { text-align: center; margin-top: 5em; font-family: Arial; }');
    $oPage->add('<div class="container"><h1>Access Denied</h1><p>You must be an administrator to access this page.</p></div>');
    $oPage->output();
    exit;
}

$oPage = new WebPage('AI Engine Diagnostics');
$oPage->set_base(utils::GetAbsoluteUrlAppRoot());

// --- Build Page Content ---
$sContent = '<div class="container">';
$sContent .= '<h1>AI Engine Diagnostics</h1>';

$sMessage = '';
$sResult = '';
$sError = '';

try {
    // --- 1. Read and Display Configuration ---
    $sContent .= '<h2>1. Configuration Status</h2>';

    $sEngineName = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.name', null);
    $aEngineConfig = MetaModel::GetConfig()->GetModuleSetting('itomig-ai-base', 'ai_engine.configuration', null);

    if ($sEngineName) {
        $sContent .= '<div class="config-item success"><span class="config-key">AI Engine Name:</span> ' . htmlspecialchars($sEngineName) . '</div>';
    } else {
        $sContent .= '<div class="config-item error"><span class="config-key">AI Engine Name:</span> Not found in config file.</div>';
    }

    if ($aEngineConfig && is_array($aEngineConfig)) {
        // Obfuscate the API key for security
        if (isset($aEngineConfig['api_key'])) {
            $aEngineConfig['api_key'] = substr($aEngineConfig['api_key'], 0, 5) . '...';
        }
        $sConfigDisplay = '<div class="config-item success"><span class="config-key">Engine Configuration:</span><pre>' . htmlspecialchars(print_r($aEngineConfig, true)) . '</pre></div>';
        $sContent .= $sConfigDisplay;
    } else {
        $sContent .= '<div class="config-item error"><span class="config-key">Engine Configuration:</span> Not found or invalid in config file.</div>';
    }

    // --- 2. Process Form Submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
        $sMessage = $_POST['message'];
        if (!empty($sMessage)) {
            $oAIService = new AIService();
            $sResult = $oAIService->GetCompletion($sMessage);
        }
    }
} catch (Exception $e) {
    $sError = "An exception occurred:\n\n" . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString();
}

// --- 3. Display Test Form ---
$sContent .= '<h2>2. Send a Test Message</h2>';
$sContent .= '<form method="POST" action="">';
$sContent .= '<textarea name="message" placeholder="Enter your test message here...">' . htmlspecialchars($sMessage) . '</textarea>';
$sContent .= '<p><input type="submit" value="Send to AI Engine"></p>';
$sContent .= '</form>';

// --- 4. Display Result or Error ---
if (!empty($sResult)) {
    $sContent .= '<h2>3. AI Response</h2>';
    $sContent .= '<div class="result">' . htmlspecialchars($sResult) . '</div>';
}

if (!empty($sError)) {
    $sContent .= '<h2>3. Error</h2>';
    $sContent .= '<div class="error-display">' . htmlspecialchars($sError) . '</div>';
}

$sContent .= '</div>';

// --- Add all content to the page and output ---
$oPage->add_style(<<<CSS
    body { font-family: Arial, sans-serif; margin: 2em; }
    .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
    h1, h2 { color: #333; }
    .config-item { margin-bottom: 10px; padding: 10px; border-radius: 3px; }
    .config-item.success { background-color: #dff0d8; border: 1px solid #d6e9c6; }
    .config-item.error { background-color: #f2dede; border: 1px solid #ebccd1; }
    .config-key { font-weight: bold; }
    textarea { width: 100%; min-height: 100px; padding: 5px; }
    input[type="submit"] { padding: 10px 15px; font-size: 16px; cursor: pointer; }
    .result, .error-display { margin-top: 20px; padding: 15px; border-radius: 3px; white-space: pre-wrap; word-wrap: break-word; }
    .result { background-color: #e7f3fe; border: 1px solid #d0e3f0; }
    .error-display { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; }
CSS
);
$oPage->add($sContent);
$oPage->output();
<?php

require_once(APPROOT.'/application/startup.inc.php');

use Itomig\iTop\Extension\AIBase\Controller\DiagnosticsController;
use Itomig\iTop\Extension\AIBase\Helper\DiagnosticsHelper;

// Instanciate the controller
$oController = new DiagnosticsController(DiagnosticsHelper::getTemplatePath(), DiagnosticsHelper::MODULE_NAME);
$oController->AllowOnlyAdmin();
$oController->SetMenuId('AIBaseDiagnostics');
$oController->SetDefaultOperation('Show');

// --- FINALES LOGGING ---
try {
    $oCtrlReflection = new \ReflectionClass($oController);
    $oPageProperty = $oCtrlReflection->getProperty('oPage');
    $oPageProperty->setAccessible(true);
    $oPage = $oPageProperty->getValue($oController);

    if (is_object($oPage)) {
        IssueLog::Info('diagnostics.php: oPage object is of class ' . get_class($oPage));
        $oPageReflection = new \ReflectionClass($oPage);
        $oTwigProperty = $oPageReflection->getProperty('oTwig');
        $oTwigProperty->setAccessible(true);
        $oTwig = $oTwigProperty->getValue($oPage);
        if ($oTwig) {
            $oLoader = $oTwig->getLoader();
            $aNamespaces = $oLoader->getNamespaces();
            IssueLog::Info('diagnostics.php: Twig namespaces found: ' . implode(', ', $aNamespaces));
            foreach ($aNamespaces as $sNamespace) {
                $aPaths = $oLoader->getPaths($sNamespace);
                IssueLog::Info('diagnostics.php: Paths for namespace "' . $sNamespace . '": ' . implode(', ', $aPaths));
            }
        }
    } else {
        IssueLog::Error('diagnostics.php: oPage is NOT an object after controller instantiation.');
    }
} catch (Exception $e) {
    IssueLog::Error('diagnostics.php: Failed to inspect controller/page: ' . $e->getMessage());
}
// --- ENDE LOGGING ---

// Handle the request
$oController->HandleOperation();
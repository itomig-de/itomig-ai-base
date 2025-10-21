<?php

require_once(APPROOT.'/application/startup.inc.php');

use Itomig\iTop\Extension\AIBase\Controller\DiagnosticsController;
use Itomig\iTop\Extension\AIBase\Helper\DiagnosticsHelper;

// Instanciate the controller
$oController = new DiagnosticsController(DiagnosticsHelper::getTemplatePath(), DiagnosticsHelper::MODULE_NAME);
$oController->AllowOnlyAdmin();
$oController->SetMenuId('AIBaseDiagnostics');
$oController->SetDefaultOperation('Show');

// Handle the request
$oController->HandleOperation();

// --- FINALES LOGGING ---
try {
    $oCtrlReflection = new \ReflectionClass($oController);
    if ($oCtrlReflection->hasProperty('oPage')) {
        $oPageProperty = $oCtrlReflection->getProperty('oPage');
        $oPageProperty->setAccessible(true);
        $oPage = $oPageProperty->getValue($oController);

        if (is_object($oPage)) {
            \IssueLog::Info('info.php: oPage object is of class ' . get_class($oPage));
            $oPageReflection = new \ReflectionClass($oPage);
            if($oPageReflection->hasProperty('oTwig')) {
                $oTwigProperty = $oPageReflection->getProperty('oTwig');
                $oTwigProperty->setAccessible(true);
                $oTwig = $oTwigProperty->getValue($oPage);
                if ($oTwig) {
                    $oLoader = $oTwig->getLoader();
                    $aNamespaces = $oLoader->getNamespaces();
                    \IssueLog::Info('info.php: Twig namespaces found: ' . implode(', ', $aNamespaces));
                    foreach ($aNamespaces as $sNamespace) {
                        $aPaths = $oLoader->getPaths($sNamespace);
                        \IssueLog::Info('info.php: Paths for namespace "' . $sNamespace . '": ' . implode(', ', $aPaths));
                    }
                }
            } else {
                \IssueLog::Error('info.php: oPage object does not have an oTwig property.');
            }
        } else {
            \IssueLog::Error('info.php: oPage is NOT an object after controller instantiation.');
        }
    } else {
        \IssueLog::Error('info.php: Controller does not have an oPage property.');
    }
} catch (Exception $e) {
    \IssueLog::Error('info.php: Failed to inspect controller/page: ' . $e->getMessage());
}
// --- ENDE LOGGING ---
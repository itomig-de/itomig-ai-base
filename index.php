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
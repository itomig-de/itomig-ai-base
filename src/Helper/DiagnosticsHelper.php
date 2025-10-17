<?php

namespace Itomig\iTop\Extension\AIBase\Helper;

class DiagnosticsHelper
{
    public const MODULE_NAME = 'itomig-ai-base';

    public static function getTemplatePath()
    {
        \IssueLog::Info("DiagnosticsHelper::getTemplatePath(): APPROOT: " . APPROOT);
        \IssueLog::Info("DiagnosticsHelper::getTemplatePath(): MODULESROOT: " . MODULESROOT);
        $sTemplatePath = MODULESROOT . self::MODULE_NAME . '/templates';
        \IssueLog::Info("DiagnosticsHelper::getTemplatePath(): returning template path: " . $sTemplatePath);
        return $sTemplatePath;
    }
}

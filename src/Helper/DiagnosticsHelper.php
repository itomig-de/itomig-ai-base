<?php

namespace Itomig\iTop\Extension\AIBase\Helper;

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
        return Utils::GetAbsoluteUrlModulesRoot() . '/' . DiagnosticsHelper::MODULE_NAME;
    }
}

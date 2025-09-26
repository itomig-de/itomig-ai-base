<?php

namespace Itomig\iTop\Extension\AIBase\Helper;

class AITools
{
    /**
     * Returns the current server date and time.
     * @return string
     */
    public static function getCurrentDate(): string
    {
        \IssueLog::Info('Called getCurrentDate.', AIBaseHelper::MODULE_CODE);
        return date('Y-m-d H:i:s');
    }

    /**
     * Returns the name of an iTop object.
     * @param \DBObject|null $oObject The iTop object.
     * @return string
     */
    public static function getObjectName(\DBObject|array|null $oObject = null): string
    {
        $oDBObject = null;

        // Case 1: A ready-to-use DBObject was passed.
        if ($oObject instanceof \DBObject) {
            $oDBObject = $oObject;
        }
        // Case 2: An array from the AI was passed.
        elseif (is_array($oObject) && isset($oObject['class']) && isset($oObject['id'])) {
            \IssueLog::Info('getObjectName received an array, attempting to load DBObject.', AIBaseHelper::MODULE_CODE, ['data' => $oObject]);
            try {
                // Attempt to load the object from the database.
                $oDBObject = \MetaModel::GetObject($oObject['class'], $oObject['id'], true);
            } catch (\Exception $e) {
                \IssueLog::Warning('getObjectName failed to load DBObject from array.', AIBaseHelper::MODULE_CODE, ['error' => $e->getMessage()]);
                return 'Error: Could not load object from provided data.';
            }
        }

        // If we still don't have an object, return a default string.
        if ($oDBObject === null) {
            \IssueLog::Info('Called getObjectName, but no valid object could be determined.', AIBaseHelper::MODULE_CODE);
            return 'No specific object context is available.';
        }

        // If we have the object, log and return its name.
        \IssueLog::Info('Called getObjectName for object of class ' . get_class($oDBObject) . ' with id ' . $oDBObject->GetKey(), AIBaseHelper::MODULE_CODE);
        return $oDBObject->GetName();
    }
}

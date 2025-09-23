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
        return date('Y-m-d H:i:s');
    }
}

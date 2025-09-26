<?php

namespace Itomig\iTop\Extension\AIBase\Helper;

class AITools
{
    private ?\DBObject $oContext = null;

    public function setContext(\DBObject $oContext): void
    {
        $this->oContext = $oContext;
        \IssueLog::Debug('AITools context set.', AIBaseHelper::MODULE_CODE, ['class' => get_class($oContext), 'id' => $oContext->GetKey()]);
    }

    /**
     * Returns the current server date and time.
     * @return string
     */
    public function getCurrentDate(): string
    {
                \IssueLog::Info('🤖 Called getCurrentDate tool.', AIBaseHelper::MODULE_CODE);
        return date('Y-m-d H:i:s');
    }

    /**
     * Returns the name of the current iTop object in context.
     * @return string
     */
    public function getName(): string
    {
                if ($this->oContext !== null) {
            \IssueLog::Info('🤖 Called getName tool for object.', AIBaseHelper::MODULE_CODE, ['class' => get_class($this->oContext), 'id' => $this->oContext->GetKey()]);
            return $this->oContext->GetName();
        }

        \IssueLog::Info('🤖 Called getName tool, but no context object was available.', AIBaseHelper::MODULE_CODE);
        return 'No specific object context is available.';
    }
}
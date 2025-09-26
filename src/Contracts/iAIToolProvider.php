<?php
// /var/www/html/itop320pro3/extensions/itomig-ai-base/src/Contracts/iAIToolProvider.php
namespace Itomig\iTop\Extension\AIBase\Contracts;

/**
 * Interface iAIToolProvider
 *
 * This interface allows other iTop extensions to provide their own tools to the AI Service.
 *
 * @package Itomig\AiBase\Contracts
 */
interface iAIToolProvider
{
    /**
     * Returns an array of tools to be registered in the AIService.
     *
     * Each element in the returned array must be another array containing exactly two elements:
     * 1. A `Theodo\Group\Llphant\Chat\FunctionInfo\FunctionInfo` object that describes the tool.
     * 2. The `callable` that executes the tool's logic.
     *
     * @return array
     *
     * @example
     * return [
     *     [ $functionInfo1, $callable1 ],
     *     [ $functionInfo2, $callable2 ],
     * ];
     */
    public function getAITools(): array;
}

<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author David Gümbel <david.guembel@itomig.de>
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with iTop. If not, see <http://www.gnu.org/licenses/>
 */

namespace Itomig\iTop\Extension\AIBase\Engine;

use Itomig\iTop\Extension\AIBase\Exception\AIResponseException;
use DeepL\Translator;
use DeepL\AppInfo;

class DeepLAIEngine implements iAIEngineInterface
{
    /**
     * @inheritDoc
     */
    public static function GetEngineName(): string
    {
        return 'DeepL';
    }

    /**
     * @inheritDoc
     */
    public static function GetPrompts(): array
    {
        return [
            [
                'label' => 'UI:AIResponse:DeepL:Prompt:Translate',
                'prompt' => 'translate'
            ]
        ];
    }

    /**
     * @inheritDoc
     */
    public static function GetEngine($configuration): DeepLAIEngine
    {
        $apiKey = $configuration['api_key'] ?? '';
        $aLanguages = $configuration['translate_languages'] ?? ['DE DE', 'EN US', 'FR FR'];
        return new self($apiKey, $aLanguages);
    }

    /**
     * @var string $apiKey
     */
    protected $apiKey;

    /**
     * @var string[] $languages
     */
    protected $languages;

    /**
     * @var Translator $translator
     */
    protected $translator;

    /**
     * @param string $apiKey
     * @param string[] $languages
     */
    public function __construct($apiKey, $languages)
    {
        $this->apiKey = $apiKey;
        $this->languages = $languages;
        $options = ['app_info' => new AppInfo('itomig-ai-base', '1.0.0')];
        $this->translator = new Translator($this->apiKey, $options);
    }

    /**
     * @inheritDoc
     * @throws AIResponseException
     */
    public function PerformPrompt($prompt, $text, $object): string
    {
        if ($prompt === 'translate') {
            return $this->translate($text);
        }
        throw new AIResponseException("Invalid prompt for DeepL: $prompt");
    }

    /**
     * Ask DeepL to translate text
     *
     * @param string $sMessage
     * @param string $targetLang
     * @return string the translated text
     * @throws AIResponseException
     */
    protected function translate($sMessage, $targetLang = "EN")
    {
        if (!in_array($targetLang, $this->languages)) {
            throw new AIResponseException("Invalid language \"$targetLang\"");
        }

        try {
            $result = $this->translator->translateText($sMessage, null, $targetLang);
            return $result->text;
        } catch (\Exception $e) {
            throw new AIResponseException("DeepL translation failed: " . $e->getMessage());
        }
    }
}

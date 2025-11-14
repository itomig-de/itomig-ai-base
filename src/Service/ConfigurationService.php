<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
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

namespace Itomig\iTop\Extension\AIBase\Service;

use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use MetaModel;
use IssueLog;
use Exception;
use utils;

/**
 * Service for managing AI Base configuration, particularly system prompts
 */
class ConfigurationService
{
	const MODULE_CODE = 'itomig-ai-base';

	/**
	 * Get current system prompts from configuration
	 * Merges built-in defaults with configured custom prompts
	 *
	 * @return array Associative array with prompt keys and their details
	 *               Format: ['key' => ['text' => '...', 'is_custom' => bool, 'is_builtin' => bool]]
	 */
	public function GetSystemPrompts(): array
	{
		$aResult = [];

		// Get built-in defaults from AIService
		$aBuiltInPrompts = AIService::DEFAULT_SYSTEM_INSTRUCTIONS;

		// Get configured prompts from config file
		$aEngineConfig = MetaModel::GetModuleSetting(self::MODULE_CODE, 'ai_engine.configuration', []);
		$aConfiguredPrompts = [];
		if (is_array($aEngineConfig) && isset($aEngineConfig['system_prompts']) && is_array($aEngineConfig['system_prompts'])) {
			$aConfiguredPrompts = $aEngineConfig['system_prompts'];
		}

		// Merge: start with built-in prompts
		foreach ($aBuiltInPrompts as $sKey => $sText) {
			$aResult[$sKey] = [
				'text' => $sText,
				'is_builtin' => true,
				'is_custom' => isset($aConfiguredPrompts[$sKey]), // Overridden in config?
			];
		}

		// Add custom prompts that aren't built-in
		foreach ($aConfiguredPrompts as $sKey => $sText) {
			if (isset($aBuiltInPrompts[$sKey])) {
				// Override built-in with configured version
				$aResult[$sKey]['text'] = $sText;
			} else {
				// Truly custom prompt
				$aResult[$sKey] = [
					'text' => $sText,
					'is_builtin' => false,
					'is_custom' => true,
				];
			}
		}

		return $aResult;
	}


	/**
	 * Validate that a prompt key contains only safe characters
	 *
	 * @param string $sKey The prompt key to validate
	 * @return bool True if valid, false otherwise
	 */
	protected function ValidatePromptKey(string $sKey): bool
	{
		// Only allow alphanumeric characters and underscores
		return preg_match('/^[a-zA-Z0-9_]+$/', $sKey) === 1;
	}

	/**
	 * Generate PHP code snippet for manual configuration update
	 *
	 * @param string $sKey The prompt key
	 * @param string $sValue The prompt value
	 * @return string PHP code snippet
	 */
	public function GenerateConfigSnippet(string $sKey, string $sValue): string
	{
		// Validate key before generating code
		if (!$this->ValidatePromptKey($sKey)) {
			return "// ERROR: Invalid prompt key '$sKey'. Only alphanumeric characters and underscores are allowed.\n";
		}

		// Use var_export for proper PHP escaping (handles newlines, quotes, etc.)
		$sEscapedValue = var_export($sValue, true);

		$sSnippet = "// Add this to your config file at: conf/" . utils::GetCurrentEnvironment() . "/config-itop.php\n";
		$sSnippet .= "// In the 'module_settings' array, under 'itomig-ai-base':\n\n";
		$sSnippet .= "'itomig-ai-base' => array(\n";
		$sSnippet .= "    'ai_engine.configuration' => array(\n";
		$sSnippet .= "        // ... existing keys (url, api_key, model) ...\n";
		$sSnippet .= "        'system_prompts' => array(\n";
		$sSnippet .= "            '$sKey' => $sEscapedValue,\n";
		$sSnippet .= "            // ... other prompts ...\n";
		$sSnippet .= "        ),\n";
		$sSnippet .= "    ),\n";
		$sSnippet .= "),\n";

		return $sSnippet;
	}
}

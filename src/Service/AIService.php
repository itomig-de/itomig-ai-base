<?php
/*
 * @copyright Copyright (C) 2024 ITOMIG GmbH
 * @license http://opensource.org/licenses/AGPL-3.0
 * @author Lars Kaltefleiter <lars.kaltefleiter@itomig.de>
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

use Itomig\iTop\Extension\AIBase\Engine\iAIEngineInterface;
use Itomig\iTop\Extension\AIBase\Helper\AIBaseHelper;
use MetaModel;
use utils;

class AIService
{
	/** @var null|string $AIEngineClass */
	protected static $AIEngineClass = null;

	/**
	 * Retrieves and returns the class name of the configured AI engine instance, if any.
	 *
	 * @return string|null The class name of the AI engine, or null if no engine is configured.
	 */
	public static function GetAIEngineClass()
	{
		if(is_null(self::$AIEngineClass))
		{
			self::$AIEngineClass = '';
			/** @var $aAIEngines */
			$AIEngineClasses = utils::GetClassesForInterface(iAIEngineInterface::class, '', array('[\\\\/]lib[\\\\/]', '[\\\\/]node_modules[\\\\/]', '[\\\\/]test[\\\\/]', '[\\\\/]tests[\\\\/]'));
			/** @var class-string<iAIEngineInterface> $AIEngineClass */
			foreach ($AIEngineClasses as $AIEngineClass)
			{
				$AIEngineName = $AIEngineClass::GetEngineName();
				if ($AIEngineName === MetaModel::GetModuleSetting(AIBaseHelper::MODULE_CODE, 'ai_engine.name', ''))
				{
					self::$AIEngineClass = $AIEngineClass;
					break;
				}
			}
		}
		return self::$AIEngineClass;
	}

	/**
	 * Retrieves and returns the prompts supported by the AI engine.
	 * // TODO 
	 *
	 * @return array An array of prompts.
	 */
	public static function GetPrompts()
	{
		/** @var class-string<iAIEngineInterface> $AIEngineClass */
		$AIEngineClass = self::GetAIEngineClass();
		if(!empty($AIEngineClass))
		{
			return $AIEngineClass::GetPrompts();
		}
		return [];
	}

	/** @var iAIEngineInterface|null $AIEngine */
	protected $AIEngine;

	public function __construct()
	{
		/** @var class-string<iAIEngineInterface> $AIEngineClass */
		$AIEngineClass = self::GetAIEngineClass();
		if(!empty($AIEngineClass))
		{
			$this->AIEngine = $AIEngineClass::GetEngine(MetaModel::GetModuleSetting(AIBaseHelper::MODULE_CODE, 'ai_engine.configuration', ''));
		}
		else
		{
			$this->AIEngine = null;
		}
	}


	/**
	 * @param string $prompt
	 * @param string $text
	 * @param \DBObject|null $srcObject
	 * @return string
	 */
	public function GetReply($prompt, $text, $srcObject = null)
	{
		if($this->AIEngine instanceof iAIEngineInterface)
		{
			return $this->AIEngine->PerformPrompt($prompt, $text, $srcObject);
		}
		return '';
	}

	/**
	 * Retrieves the AI engine instance.
	 *
	 * @return iAIEngineInterface|null The AI engine instance, or null if no engine is configured.
	 */
	public function GetAIEngine() {
		return $this->AIEngine;
	}
}

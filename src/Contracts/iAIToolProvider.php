<?php
/*
 * @copyright Copyright (C) 2024, 2025 ITOMIG GmbH
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

namespace Itomig\iTop\Extension\AIBase\Contracts;

use LLPhant\Tool\FunctionInfo;

/**
 * Interface for external Tool Providers that can register AI tools.
 *
 * Implement this interface in your extension to provide custom AI tools
 * that can be used in multi-turn conversations. Tools are discovered
 * automatically via iTop's InterfaceDiscovery mechanism.
 *
 * Example implementation:
 * ```php
 * class MyToolProvider implements iAIToolProvider
 * {
 *     public function getAITools(): array
 *     {
 *         return [
 *             new FunctionInfo(
 *                 'myTool',
 *                 $this,
 *                 'Description of what the tool does',
 *                 [new Parameter('param1', 'string', 'Parameter description')]
 *             ),
 *         ];
 *     }
 * }
 * ```
 */
interface iAIToolProvider
{
	/**
	 * Returns an array of FunctionInfo objects representing the tools provided by this provider.
	 *
	 * @return FunctionInfo[] Array of FunctionInfo objects that can be used by the AI.
	 */
	public function getAITools(): array;
}

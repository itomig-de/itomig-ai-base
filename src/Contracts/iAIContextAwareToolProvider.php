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

use DBObject;

/**
 * Interface for tool providers that require an iTop object context.
 *
 * Implement this interface alongside iAIToolProvider when your tools need
 * access to a specific DBObject during conversation. The AIService will
 * call setContext() before each conversation turn.
 *
 * Example:
 * ```php
 * class MyContextTools implements iAIToolProvider, iAIContextAwareToolProvider
 * {
 *     private ?DBObject $oContext = null;
 *
 *     public function setContext(?DBObject $oObject): void
 *     {
 *         $this->oContext = $oObject;
 *     }
 *
 *     public function getAITools(): array { ... }
 * }
 * ```
 */
interface iAIContextAwareToolProvider
{
	/**
	 * Set the iTop object context for this tool provider.
	 *
	 * Called by AIService before each conversation turn when an object is available.
	 *
	 * @param DBObject|null $oObject The iTop object to use as context, or null to clear.
	 */
	public function setContext(?DBObject $oObject): void;
}

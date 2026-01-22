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

namespace Itomig\iTop\Extension\AIBase\Helper;

use DBObject;
use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\FunctionInfo\Parameter;

/**
 * Default AI tools for interacting with iTop DBObjects.
 *
 * These tools are read-only and safe for AI use. They provide access to
 * object properties, state information, and available transitions.
 *
 * SECURITY NOTE: Only read-only methods are exposed. Write operations
 * (Set, ApplyStimulus, DBWrite) are intentionally not included.
 */
class AIObjectTools
{
	private ?DBObject $oContext = null;

	/**
	 * Set the iTop object context for the tools.
	 *
	 * @param DBObject|null $oObject The iTop object to use as context.
	 */
	public function setContext(?DBObject $oObject): void
	{
		$this->oContext = $oObject;
	}

	/**
	 * Get the friendly name of the current object.
	 *
	 * @return string The object's friendly name, or error message if no context.
	 */
	public function getObjectName(): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		return $this->oContext->GetName();
	}

	/**
	 * Get the ID of the current object.
	 *
	 * @return int The object's ID, or 0 if no context.
	 */
	public function getObjectId(): int
	{
		if ($this->oContext === null) {
			return 0;
		}
		return (int) $this->oContext->GetKey();
	}

	/**
	 * Get the class of the current object.
	 *
	 * @return string The object's class name, or error message if no context.
	 */
	public function getObjectClass(): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		return get_class($this->oContext);
	}

	/**
	 * Get an attribute value from the current object.
	 *
	 * @param string $attributeCode The attribute code (e.g., 'title', 'description', 'org_id').
	 * @return string The attribute value, or error message.
	 */
	public function getAttribute(string $attributeCode): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			$value = $this->oContext->Get($attributeCode);
			if (is_object($value)) {
				return (string) $value;
			}
			return (string) $value;
		} catch (\Exception $e) {
			return "Attribute '$attributeCode' not found or not accessible";
		}
	}

	/**
	 * Get the label (display name) of an attribute definition.
	 *
	 * @param string $attributeCode The attribute code.
	 * @return string The attribute's label, or error message.
	 */
	public function getAttributeLabel(string $attributeCode): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			return $this->oContext->GetLabel($attributeCode);
		} catch (\Exception $e) {
			return "Attribute '$attributeCode' not found";
		}
	}

	/**
	 * Get the current lifecycle state of the object.
	 *
	 * @return string The current state code, or error message.
	 */
	public function getState(): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$sState = $this->oContext->GetState();
		if (empty($sState)) {
			return 'Object has no lifecycle state';
		}
		return $sState;
	}

	/**
	 * Get the human-readable label of the current state.
	 *
	 * @return string The state label, or error message.
	 */
	public function getStateLabel(): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$sLabel = $this->oContext->GetStateLabel();
		if (empty($sLabel)) {
			return 'Object has no lifecycle state';
		}
		return $sLabel;
	}

	/**
	 * List all available transitions from the current state.
	 *
	 * @return string Comma-separated list of transition codes, or message if none available.
	 */
	public function getAvailableTransitions(): string
	{
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$aTransitions = $this->oContext->EnumTransitions();
		if (empty($aTransitions)) {
			return 'No transitions available';
		}
		return implode(', ', array_keys($aTransitions));
	}

	/**
	 * Get the current date and time.
	 *
	 * @return string Current date and time in ISO 8601 format.
	 */
	public function getCurrentDateTime(): string
	{
		return date('Y-m-d H:i:s');
	}

	/**
	 * Creates and returns an array of FunctionInfo objects for all available tools.
	 *
	 * @return FunctionInfo[] Array of FunctionInfo objects ready for use with LLPhant.
	 */
	public function getToolDefinitions(): array
	{
		return [
			new FunctionInfo(
				'getObjectName',
				$this,
				'Get the friendly name of the iTop object the user is currently looking at.',
				[],
				[]
			),
			new FunctionInfo(
				'getObjectId',
				$this,
				'Get the unique ID of the current iTop object.',
				[],
				[]
			),
			new FunctionInfo(
				'getObjectClass',
				$this,
				'Get the class name (type) of the current iTop object.',
				[],
				[]
			),
			new FunctionInfo(
				'getAttribute',
				$this,
				'Get the value of a specific attribute from the current iTop object. Common attributes include: title, description, status, org_id, caller_id, team_id, agent_id, start_date, end_date, etc.',
				[new Parameter('attributeCode', 'string', 'The attribute code to retrieve (e.g., "title", "description", "status")')],
				[new Parameter('attributeCode', 'string', 'The attribute code to retrieve')]
			),
			new FunctionInfo(
				'getAttributeLabel',
				$this,
				'Get the human-readable label (display name) of an attribute definition.',
				[new Parameter('attributeCode', 'string', 'The attribute code to get the label for')],
				[new Parameter('attributeCode', 'string', 'The attribute code')]
			),
			new FunctionInfo(
				'getState',
				$this,
				'Get the current lifecycle state code of the iTop object (e.g., "new", "assigned", "resolved").',
				[],
				[]
			),
			new FunctionInfo(
				'getStateLabel',
				$this,
				'Get the human-readable label of the current lifecycle state.',
				[],
				[]
			),
			new FunctionInfo(
				'getAvailableTransitions',
				$this,
				'List all available state transitions (actions) that can be performed on the current object from its current state.',
				[],
				[]
			),
			new FunctionInfo(
				'getCurrentDateTime',
				$this,
				'Get the current server date and time.',
				[],
				[]
			),
		];
	}
}

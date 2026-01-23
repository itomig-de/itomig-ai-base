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
use IssueLog;
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
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$result = $this->oContext->GetName();
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
	}

	/**
	 * Get the ID of the current object.
	 *
	 * @return int The object's ID, or 0 if no context.
	 */
	public function getObjectId(): int
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 0;
		}
		$result = (int) $this->oContext->GetKey();
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
	}

	/**
	 * Get the class of the current object.
	 *
	 * @return string The object's class name, or error message if no context.
	 */
	public function getObjectClass(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$result = get_class($this->oContext);
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
	}

	/**
	 * Get an attribute value from the current object.
	 *
	 * @param string $attributeCode The attribute code (e.g., 'title', 'description', 'org_id').
	 * @return string The attribute value, or error message.
	 */
	public function getAttribute(string $attributeCode): string
	{
		IssueLog::Debug(__METHOD__ . ": Called with '$attributeCode', context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			$value = $this->oContext->Get($attributeCode);
			if (is_object($value)) {
				$result = (string) $value;
			} else {
				$result = (string) $value;
			}
			IssueLog::Debug(__METHOD__ . ": Returning: " . substr($result, 0, 100), AIBaseHelper::MODULE_CODE);
			return $result;
		} catch (\Exception $e) {
			$error = "Attribute '$attributeCode' not found or not accessible";
			IssueLog::Debug(__METHOD__ . ": Error: " . $error, AIBaseHelper::MODULE_CODE);
			return $error;
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
		IssueLog::Debug(__METHOD__ . ": Called with '$attributeCode', context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			$result = $this->oContext->GetLabel($attributeCode);
			IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
			return $result;
		} catch (\Exception $e) {
			$error = "Attribute '$attributeCode' not found";
			IssueLog::Debug(__METHOD__ . ": Error: " . $error, AIBaseHelper::MODULE_CODE);
			return $error;
		}
	}

	/**
	 * Get the current lifecycle state of the object.
	 *
	 * @return string The current state code, or error message.
	 */
	public function getState(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$sState = $this->oContext->GetState();
		if (empty($sState)) {
			return 'Object has no lifecycle state';
		}
		IssueLog::Debug(__METHOD__ . ": Returning: " . $sState, AIBaseHelper::MODULE_CODE);
		return $sState;
	}

	/**
	 * Get the human-readable label of the current state.
	 *
	 * @return string The state label, or error message.
	 */
	public function getStateLabel(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$sLabel = $this->oContext->GetStateLabel();
		if (empty($sLabel)) {
			return 'Object has no lifecycle state';
		}
		IssueLog::Debug(__METHOD__ . ": Returning: " . $sLabel, AIBaseHelper::MODULE_CODE);
		return $sLabel;
	}

	/**
	 * List all available transitions from the current state.
	 *
	 * @return string Comma-separated list of transition codes, or message if none available.
	 */
	public function getAvailableTransitions(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		$aTransitions = $this->oContext->EnumTransitions();
		if (empty($aTransitions)) {
			return 'No transitions available';
		}
		$result = implode(', ', array_keys($aTransitions));
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
	}

	/**
	 * Get the current date and time.
	 *
	 * @return string Current date and time in ISO 8601 format.
	 */
	public function getCurrentDateTime(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called (no context needed)", AIBaseHelper::MODULE_CODE);
		$result = date('Y-m-d H:i:s');
		IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
		return $result;
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
				'Get the friendly name. No parameters required - context is provided automatically.',
				[],
				[]
			),
			new FunctionInfo(
				'getObjectId',
				$this,
				'Get the unique ID. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getObjectClass',
				$this,
				'Get the class name (type). No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getAttribute',
				$this,
				'Get the value of a specific attribute. Requires: attributeCode (string). Common attributes: title, description, status, org_id, caller_id, team_id, agent_id, start_date, end_date.',
				[new Parameter('attributeCode', 'string', 'The attribute code to retrieve (e.g., "title", "description", "status")')],
				[new Parameter('attributeCode', 'string', 'The attribute code to retrieve')]
			),
			new FunctionInfo(
				'getAttributeLabel',
				$this,
				'Get the label of an attribute. Requires: attributeCode (string).',
				[new Parameter('attributeCode', 'string', 'The attribute code to get the label for')],
				[new Parameter('attributeCode', 'string', 'The attribute code')]
			),
			new FunctionInfo(
				'getState',
				$this,
				'Get the current lifecycle state code (e.g., "new", "assigned", "resolved"). No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getStateLabel',
				$this,
				'Get the human-readable state label. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getAvailableTransitions',
				$this,
				'List available state transitions. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'getCurrentDateTime',
				$this,
				'Get current server date and time. No parameters required.',
				[],
				[]
			),
		];
	}
}

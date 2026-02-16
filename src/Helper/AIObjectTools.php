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

use AttributeEnum;
use AttributeExternalField;
use AttributeExternalKey;
use AttributeLinkedSet;
use DBObject;
use IssueLog;
use Itomig\iTop\Extension\AIBase\Contracts\iAIContextAwareToolProvider;
use Itomig\iTop\Extension\AIBase\Contracts\iAIToolProvider;
use LLPhant\Chat\FunctionInfo\FunctionInfo;
use LLPhant\Chat\FunctionInfo\Parameter;
use MetaModel;

/**
 * Default AI tools for interacting with iTop DBObjects.
 *
 * These tools are read-only and safe for AI use. They provide access to
 * object properties and attribute information.
 *
 * Lifecycle-specific tools (getState, getStateLabel, getAvailableTransitions) are
 * available as methods but not registered as default AI tools, since not all
 * iTop objects have a lifecycle. Extensions can register them selectively.
 *
 * SECURITY NOTE: Only read-only methods are exposed. Write operations
 * (Set, ApplyStimulus, DBWrite) are intentionally not included.
 */
class AIObjectTools implements iAIToolProvider, iAIContextAwareToolProvider
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
	 * @return string The object's ID, or '0' if no context.
	 */
	public function getObjectId(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return '0';
		}
		$result = (string) $this->oContext->GetKey();
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
	 * Get a JSON schema describing the current object's class and all its attributes.
	 *
	 * Returns attribute codes, labels, types, and descriptions so the LLM can
	 * discover which attributes are available for getAttribute().
	 *
	 * @return string JSON-encoded schema, or JSON error if no context.
	 */
	public function describeObject(): string
	{
		IssueLog::Debug(__METHOD__ . ": Called, context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return json_encode(['error' => 'No object in context']);
		}

		$sClass = get_class($this->oContext);
		$aSchema = [
			'class' => $sClass,
			'class_label' => MetaModel::GetName($sClass),
			'class_description' => MetaModel::GetClassDescription($sClass),
			'attributes' => [],
		];

		foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
			if ($sAttCode === 'id') {
				continue;
			}

			$aAttrInfo = [
				'label' => $oAttDef->GetLabel(),
				'description' => $oAttDef->GetDescription(),
				'type' => self::MapAttributeType($oAttDef),
			];

			if ($oAttDef instanceof AttributeExternalKey) {
				$aAttrInfo['target_class'] = $oAttDef->GetTargetClass();
			} elseif ($oAttDef instanceof AttributeExternalField) {
				$aAttrInfo['ext_key_attcode'] = $oAttDef->GetKeyAttCode();
			} elseif ($oAttDef instanceof AttributeEnum) {
				$aAllowedValues = [];
				$oValuesDef = $oAttDef->GetValuesDef();
				if ($oValuesDef !== null) {
					$aRawValues = $oValuesDef->GetValues([], '');
					foreach (array_keys($aRawValues) as $sValue) {
						$aAllowedValues[$sValue] = $oAttDef->GetValueLabel($sValue);
					}
				}
				$aAttrInfo['allowed_values'] = $aAllowedValues;
			} elseif ($oAttDef instanceof AttributeLinkedSet) {
				$aAttrInfo['linked_class'] = $oAttDef->GetLinkedClass();
			}

			$aSchema['attributes'][$sAttCode] = $aAttrInfo;
		}

		IssueLog::Debug(__METHOD__ . ": Returning schema for class $sClass with " . count($aSchema['attributes']) . " attributes", AIBaseHelper::MODULE_CODE);
		return json_encode($aSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	/**
	 * Maps an iTop attribute definition to an LLM-friendly type name.
	 *
	 * @param \AttributeDefinition $oAttDef The attribute definition to map.
	 * @return string The LLM-friendly type name.
	 */
	public static function MapAttributeType(\AttributeDefinition $oAttDef): string
	{
		return match (true) {
			$oAttDef instanceof \AttributeLinkedSet => 'LinkedSet',
			$oAttDef instanceof \AttributeExternalKey => 'ExternalKey',
			$oAttDef instanceof \AttributeExternalField => 'ExternalField',
			$oAttDef instanceof \AttributeEnum => 'Enum',
			$oAttDef instanceof \AttributeBoolean => 'Boolean',
			$oAttDef instanceof \AttributeInteger => 'Integer',
			$oAttDef instanceof \AttributeDecimal => 'Decimal',
			$oAttDef instanceof \AttributeDateTime => 'DateTime',
			$oAttDef instanceof \AttributeDate => 'Date',
			$oAttDef instanceof \AttributeEmailAddress => 'Email',
			$oAttDef instanceof \AttributeURL => 'URL',
			$oAttDef instanceof \AttributeIPAddress => 'IPAddress',
			$oAttDef instanceof \AttributePhoneNumber => 'PhoneNumber',
			$oAttDef instanceof \AttributePassword,
			$oAttDef instanceof \AttributeEncryptedString => 'Password',
			default => 'String',
		};
	}

	/**
	 * Returns an array of FunctionInfo objects for the default AI tools.
	 *
	 * Note: Lifecycle tools (getState, getStateLabel, getAvailableTransitions) are not
	 * included here as they only work on objects with a lifecycle. The methods remain
	 * available on this class for use by lifecycle-aware extensions.
	 *
	 * @return FunctionInfo[] Array of FunctionInfo objects ready for use with LLPhant.
	 */
	public function getAITools(): array
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
				'getCurrentDateTime',
				$this,
				'Get current server date and time. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'describeObject',
				$this,
				'Get a JSON schema describing the current object\'s class, all attributes with their codes, labels, types, and descriptions. Call this to discover which attribute codes are available for getAttribute(). No parameters required.',
				[],
				[]
			),
		];
	}
}

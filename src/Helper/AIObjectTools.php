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

use AttributeCustomFields;
use AttributeEnum;
use AttributeExternalField;
use AttributeExternalKey;
use AttributeLinkedSet;
use AttributeTagSet;
use DBObject;
use ormCustomFieldsValue;
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
 * object properties and attribute information. All tools require an object context.
 *
 * Context-free tools (get_current_date_time, get_current_user, get_current_user_profiles)
 * have been moved to AISystemTools.
 *
 * SECURITY NOTE: Only read-only methods are exposed. Write operations
 * (Set, ApplyStimulus, DBWrite) are intentionally not included.
 *
 * Credential filtering has a single authority here: IsAttributeSafeForAI() is
 * the public entry point for any extension that builds AI prompts from
 * attribute lists — it resolves external fields and withholds credential-
 * bearing attributes (see SENSITIVE_ATTRIBUTE_TYPES).
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
	public function get_object_name(): string
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
	public function get_object_id(): string
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
	public function get_object_class(): string
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
	 * Attribute types whose value is never handed to the model.
	 *
	 * Matched with instanceof, so subclasses are covered too. AttributeOneWayPassword
	 * is included even though it only ever yields a hash: a hash is still a credential
	 * artefact and has no business in a prompt.
	 */
	private const SENSITIVE_ATTRIBUTE_TYPES = [
		'AttributePassword',
		'AttributeEncryptedString',
		'AttributeOneWayPassword',
	];

	/**
	 * Get an attribute value from the current object.
	 *
	 * Credential-bearing attributes return an empty string rather than their value —
	 * see SENSITIVE_ATTRIBUTE_TYPES. Deliberately not an error: the model should carry
	 * on answering instead of being nudged into working around a refusal.
	 *
	 * @param string $attribute_code The attribute code (e.g., 'title', 'description', 'org_id').
	 * @return string The attribute value, an empty string if the attribute is
	 *                credential-bearing, or an error message.
	 */
	public function get_attribute(string $attribute_code): string
	{
		IssueLog::Debug(__METHOD__ . ": Called with '$attribute_code', context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			$result = self::ResolveFilteredAttributeValue($this->oContext, $attribute_code);
			IssueLog::Debug(__METHOD__ . ": Returning: " . substr($result, 0, 100), AIBaseHelper::MODULE_CODE);
			return $result;
		} catch (\Exception $e) {
			$error = "Attribute '$attribute_code' not found or not accessible";
			IssueLog::Debug(__METHOD__ . ": Error: " . $error, AIBaseHelper::MODULE_CODE);
			return $error;
		}
	}

	/**
	 * Resolves a single attribute's string value, withholding it if it is credential-bearing.
	 *
	 * The one place that decides what "sensitive" means for both get_attribute() and
	 * GetFilteredAttributeValues() — neither call site re-implements the check, so a
	 * caller reading attributes through either path cannot forget to apply it.
	 *
	 * Custom fields (AttributeCustomFields) render as a JSON string holding the same
	 * structure the core REST GetObject path produces ({"template_id": ..., "values":
	 * {...}}) — instead of crashing on the string cast.
	 *
	 * Link sets (AttributeLinkedSet / AttributeLinkedSetIndirect) and tag sets
	 * (AttributeTagSet) render the same way the core REST/JSON export does: an
	 * array of per-link entries resp. an array of tag codes (see
	 * MakeResultValueAsArray). Their string cast is unusable for a prompt — an
	 * empty string resp. a lossy ", "-join — and collapsed into the same value
	 * callers use to recognize withheld credentials (#85).
	 *
	 * @param DBObject $oObject The object to read the attribute from.
	 * @param string $sAttCode The attribute code. Must be valid for $oObject's class.
	 * @return string The attribute value. Renderable structures are returned as
	 *                JSON strings ('[]' for an empty link set, NOT ''). An empty
	 *                string means — and only means — credential-bearing.
	 * @throws \Exception if $sAttCode is not a valid attribute of $oObject's class.
	 */
	private static function ResolveFilteredAttributeValue(DBObject $oObject, string $sAttCode): string
	{
		// Before Get(), so the value is never even read into memory. An unknown
		// attribute code throws here just as Get() would, keeping that path unchanged.
		$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);

		// An external field carries the value of an attribute on another class, and
		// its own type says nothing about it: MailInboxOAuth::client_secret is an
		// AttributeExternalField whose target on OAuthClient is an AttributePassword.
		// Without resolving, the filter would wave the secret straight through.
		if ($oAttDef instanceof AttributeExternalField) {
			$oAttDef = $oAttDef->GetFinalAttDef();
		}

		if (self::IsSensitiveAttribute($oAttDef)) {
			// Info rather than Debug: an operator should see a blocked credential
			// access without having to switch on debug logging first. The value is
			// deliberately not logged, and neither is anything else identifying.
			IssueLog::Info(
				__METHOD__ . ": Withheld credential-bearing attribute '$sAttCode' ("
					. get_class($oAttDef) . ') from the caller.',
				AIBaseHelper::MODULE_CODE
			);

			return '';
		}

		// Custom fields (Request Templates: service_details on UserRequest & co.)
		// yield an ormCustomFieldsValue object, which is not string-castable — the
		// plain cast below threw "Object of class ormCustomFieldsValue could not be
		// converted to string" for every ticket with a request template
		// (itomig-de/itomig-ai-base#76, surfaced via itomig-de/itomig-ai-diagnostics#11).
		// GetForJSON() is what the core's own REST GetObject path uses for these
		// attributes (ObjectResult::MakeResultValue → AttributeCustomFields::GetForJSON),
		// so the model sees the SAME structure here as from GetTicketData() / the
		// REST API: {"template_id": ..., "values": {...}}. A readable text rendering
		// (GetForTemplate) would flatten that structure and diverge from the rest
		// of the stack.
		if ($oAttDef instanceof AttributeCustomFields) {
			$oValue = $oObject->Get($sAttCode);
			if ($oValue instanceof ormCustomFieldsValue) {
				try {
					return (string) json_encode($oValue->GetForJSON(), JSON_UNESCAPED_UNICODE);
				} catch (\Exception $e) {
					// Same fallback the core AttributeCustomFields::GetForJSON uses:
					// a misbehaving handler must not take the whole read down.
					return 'Custom field error: '.$e->getMessage();
				}
			}

			return '';
		}

		// Tag sets (AttributeTagSet, e.g. domains on FAQ): Get() yields an ormTagSet
		// whose string cast joins the tag codes with ", " — boundaries are lost, a
		// tag containing a space becomes indistinguishable from two tags, and an
		// EMPTY set casts to a single space ' ', not '' (#85, Case 3).
		// GetForJSON() is what the core's own REST/JSON export uses for these
		// attributes (ObjectResult::MakeResultValue → AttributeTagSet::GetForJSON),
		// so the model receives a JSON array of tag codes — unambiguous both ways.
		if ($oAttDef instanceof AttributeTagSet) {
			return self::EncodeAsJSON($oAttDef->GetForJSON($oObject->Get($sAttCode)));
		}

		// Link sets (AttributeLinkedSet / AttributeLinkedSetIndirect, e.g.
		// functionalcis_list on UserRequest): Get() yields an ormLinkSet whose
		// __toString() returns '' regardless of its content, so the field was
		// indistinguishable from a withheld credential value and callers screening
		// with ($value !== '') discarded it (#85, Case 2).
		// Rendered like the core's own REST/JSON export (see MakeResultValueAsArray
		// below), so the model receives the same per-link structure the REST API
		// would produce, with the back-references to the host object skipped.
		if ($oAttDef instanceof AttributeLinkedSet) {
			return self::EncodeAsJSON(self::MakeResultValueAsArray($oObject, $sAttCode));
		}

		return (string) $oObject->Get($sAttCode);
	}

	/**
	 * JSON-encodes a rendered structure, or the JSON literal "null" should the
	 * encoder fail.
	 *
	 * ResolveFilteredAttributeValue() MUST never return '' for a representable
	 * value: '' is reserved for "withheld because credential-bearing", and a
	 * caller cannot tell the two apart (#85). json_encode() returns false on
	 * failure, which a cast would turn into exactly that ambiguous empty string.
	 *
	 * @param mixed $aData A structure built with the core's REST/JSON semantics.
	 * @return string The JSON encoding of $aData, or 'null'.
	 */
	private static function EncodeAsJSON($aData): string
	{
		$sJson = json_encode($aData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

		return ($sJson === false) ? 'null' : $sJson;
	}

	/**
	 * Builds the REST/JSON representation of an attribute value, mirroring the
	 * core's ObjectResult::MakeResultValue() in core/restservices.class.inc.php
	 * with its default (non-extended) output mode.
	 *
	 * Own semantics, copied deliberately instead of re-invented:
	 *  - link sets (AttributeLinkedSet, incl. AttributeLinkedSetIndirect) get a
	 *    dedicated recursive branch: an array of per-link {attcode => value}
	 *    entries, in which attributes pointing back to the host object are
	 *    skipped (the external key itself, and any external field resolving to
	 *    it). Link sets therefore never go through GetForJSON() — the core does
	 *    the same, because ormLinkSet cannot be serialised that way.
	 *  - everything else goes through the attribute definition's GetForJSON(),
	 *    i.e. exactly the representation the REST/JSON API produces for the
	 *    same object: tag sets become arrays of tag codes, case logs become
	 *    {entries: [...]}, custom fields keep their {template_id, values}
	 *    structure, scalars stay scalars.
	 *
	 * A model prompt built from this rendering is therefore consistent with
	 * what the same caller would obtain from the REST API for the same object.
	 *
	 * @param DBObject $oObject The object to read the attribute from.
	 * @param string $sAttCode The attribute code. Must be valid for $oObject's class.
	 * @return mixed The JSON-encodable representation of the attribute value.
	 */
	private static function MakeResultValueAsArray(DBObject $oObject, string $sAttCode)
	{
		if ($sAttCode === 'id') {
			return $oObject->GetKey();
		}

		$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);
		if ($oAttDef instanceof AttributeLinkedSet) {
			// Iterate on the set and build an array of array of attcode => value
			$value = array();
			$oSet = $oObject->Get($sAttCode);
			$sLinkedClass = $oAttDef->GetLinkedClass();
			$sExtKeyToMe = $oAttDef->GetExtKeyToMe();
			while ($oLnk = $oSet->Fetch()) {
				$aLnkValues = array();
				foreach (MetaModel::ListAttributeDefs($sLinkedClass) as $sLnkAttCode => $oLnkAttDef) {
					// Skip attributes pointing to the current object (redundant data)
					if ($sLnkAttCode == $sExtKeyToMe) {
						continue;
					}
					// Skip any attribute of the link that points to the current object
					$oLnkAttDef = MetaModel::GetAttributeDef($sLinkedClass, $sLnkAttCode);
					if (method_exists($oLnkAttDef, 'GetKeyAttCode')) {
						if ($oLnkAttDef->GetKeyAttCode() == $sExtKeyToMe) {
							continue;
						}
					}

					$aLnkValues[$sLnkAttCode] = self::MakeResultValueAsArray($oLnk, $sLnkAttCode);
				}
				$value[] = $aLnkValues;
			}

			return $value;
		}

		return $oAttDef->GetForJSON($oObject->Get($sAttCode));
	}


	/**
	 * Returns string values for several attributes of an object, withholding
	 * credential-bearing ones.
	 *
	 * For any consumer — inside or outside this extension — that needs to read more
	 * than one attribute off a DBObject for display or for an AI prompt. Built because
	 * a consumer collecting attributes through MetaModel::ListAttributeDefs() + Get()
	 * directly, instead of through this method, has no way to know which attribute
	 * types are credential-bearing and reliably reintroduces the leak get_attribute()
	 * exists to prevent.
	 *
	 * @param DBObject $oObject The object to read.
	 * @param string[]|null $aAttCodes Attribute codes to read, or null for every
	 *                                 attribute of $oObject's class.
	 * @return array<string, string> Attribute code => value. Credential-bearing
	 *                                attributes (see SENSITIVE_ATTRIBUTE_TYPES) map to
	 *                                an empty string; invalid attribute codes are skipped.
	 */
	public static function GetFilteredAttributeValues(DBObject $oObject, ?array $aAttCodes = null): array
	{
		$sClass = get_class($oObject);
		$aAttCodes ??= array_keys(MetaModel::ListAttributeDefs($sClass));

		$aResult = [];
		foreach ($aAttCodes as $sAttCode) {
			if (!MetaModel::IsValidAttCode($sClass, $sAttCode)) {
				continue;
			}
			$aResult[$sAttCode] = self::ResolveFilteredAttributeValue($oObject, $sAttCode);
		}

		return $aResult;
	}

	/**
	 * Get the label (display name) of an attribute definition.
	 *
	 * @param string $attribute_code The attribute code.
	 * @return string The attribute's label, or error message.
	 */
	public function get_attribute_label(string $attribute_code): string
	{
		IssueLog::Debug(__METHOD__ . ": Called with '$attribute_code', context=" . ($this->oContext ? 'SET' : 'NULL'), AIBaseHelper::MODULE_CODE);
		if ($this->oContext === null) {
			return 'No object in context';
		}
		try {
			$result = $this->oContext->GetLabel($attribute_code);
			IssueLog::Debug(__METHOD__ . ": Returning: " . $result, AIBaseHelper::MODULE_CODE);
			return $result;
		} catch (\Exception $e) {
			$error = "Attribute '$attribute_code' not found";
			IssueLog::Debug(__METHOD__ . ": Error: " . $error, AIBaseHelper::MODULE_CODE);
			return $error;
		}
	}

	/**
	 * Get a JSON schema describing the current object's class and all its attributes.
	 *
	 * Returns attribute codes, labels, types, and descriptions so the LLM can
	 * discover which attributes are available for get_attribute().
	 *
	 * @return string JSON-encoded schema, or JSON error if no context.
	 */
	public function describe_object(): string
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
	 * Whether an attribute's value must be withheld from the model.
	 *
	 * @param \AttributeDefinition $oAttDef The attribute definition to check. Must
	 *                                      already be resolved (external fields
	 *                                      resolved to their final att def) — use
	 *                                      IsAttributeSafeForAI() for the
	 *                                      resolution-aware public entry point.
	 * @return bool True if the attribute is credential-bearing.
	 */
	private static function IsSensitiveAttribute(\AttributeDefinition $oAttDef): bool
	{
		foreach (self::SENSITIVE_ATTRIBUTE_TYPES as $sClass) {
			if ($oAttDef instanceof $sClass) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an attribute's value is safe to hand to the model — the single
	 * authority for credential filtering, usable by any extension reading
	 * attribute values for AI prompts.
	 *
	 * Resolves external fields to their final attribute definition first, so a
	 * field pointing at a credential-bearing attribute on another class is
	 * correctly recognized (MailInboxOAuth::client_secret → OAuthClient password).
	 * An unknown attribute code throws, mirroring GetAttributeDef().
	 *
	 * @param DBObject $oObject The object whose class the attribute is read from.
	 * @param string $sAttCode The attribute code to check.
	 * @return bool True if the attribute value may be included in an AI prompt.
	 * @throws \Exception if $sAttCode is not a valid attribute of $oObject's class.
	 */
	public static function IsAttributeSafeForAI(DBObject $oObject, string $sAttCode): bool
	{
		$oAttDef = MetaModel::GetAttributeDef(get_class($oObject), $sAttCode);

		if ($oAttDef instanceof AttributeExternalField) {
			$oAttDef = $oAttDef->GetFinalAttDef();
		}

		return !self::IsSensitiveAttribute($oAttDef);
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
			$oAttDef instanceof AttributeCustomFields => 'CustomFields',
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
	 * @return FunctionInfo[] Array of FunctionInfo objects ready for use with LLPhant.
	 */
	public function getAITools(): array
	{
		return [
			new FunctionInfo(
				'get_object_name',
				$this,
				'Get the friendly name. No parameters required - context is provided automatically.',
				[],
				[]
			),
			new FunctionInfo(
				'get_object_id',
				$this,
				'Get the unique ID. No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'get_object_class',
				$this,
				'Get the class name (type). No parameters required.',
				[],
				[]
			),
			new FunctionInfo(
				'get_attribute',
				$this,
				'Get the value of a specific attribute. Requires: attribute_code (string). Common attributes: title, description, status, org_id, caller_id, team_id, agent_id, start_date, end_date.',
				[new Parameter('attribute_code', 'string', 'The attribute code to retrieve (e.g., "title", "description", "status")')],
				[new Parameter('attribute_code', 'string', 'The attribute code to retrieve')]
			),
			new FunctionInfo(
				'get_attribute_label',
				$this,
				'Get the label of an attribute. Requires: attribute_code (string).',
				[new Parameter('attribute_code', 'string', 'The attribute code to get the label for')],
				[new Parameter('attribute_code', 'string', 'The attribute code')]
			),
			new FunctionInfo(
				'describe_object',
				$this,
				'Get a JSON schema describing the current object\'s class, all attributes with their codes, labels, types, and descriptions. Call this to discover which attribute codes are available for get_attribute(). No parameters required.',
				[],
				[]
			),
		];
	}
}

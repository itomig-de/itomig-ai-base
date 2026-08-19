<?php
/**
 * Tests for the credential filter in AIObjectTools.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use AttributeExternalField;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Helper\AIObjectTools;
use MetaModel;

/**
 * The filter is a security control: it decides which attribute values may be
 * handed to a language model. Both entry points must apply it —
 * get_attribute() for tool-calling, and GetFilteredAttributeValues() for
 * consumers reading several attributes at once. Neither had test coverage.
 *
 * Two design decisions worth stating:
 *
 *  1. The classes under test are DISCOVERED from the datamodel instead of being
 *     hard-coded. Which class carries an AttributePassword depends on the
 *     installed modules (OAuthClientAzure only exists with the OAuth client
 *     module), and ai-base has to work on installations that differ. A test
 *     naming concrete classes would fail for the wrong reason elsewhere.
 *  2. No object is written to the database. The filter decides on the attribute
 *     DEFINITION, not on stored data, so an object held in memory exercises
 *     exactly the same code path and the test leaves no trace.
 */
class AIObjectToolsTest extends ItopDataTestCase
{
	/**
	 * Fully qualified, because that is what class_implements() and instanceof
	 * resolve against. Comparing the bare interface name silently never matches.
	 */
	private const SENSITIVE_TYPES = [
		'Combodo\iTop\Core\AttributeDefinition\AttributePassword',
		'Combodo\iTop\Core\AttributeDefinition\AttributeEncryptedString',
		'Combodo\iTop\Core\AttributeDefinition\AttributeOneWayPassword',
	];

	/**
	 * Deliberately recognisable. If this string ever turns up in a log, a prompt
	 * or a test report, it is obvious where it came from and that it is not real.
	 */
	private const PROBE = 'DUMMY-NOT-A-REAL-SECRET-c7f21a';

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	public function SensitiveTypeProvider(): array
	{
		$aCases = [];
		foreach (self::SENSITIVE_TYPES as $sType) {
			$aCases[substr($sType, strrpos($sType, '\\') + 1)] = [$sType];
		}

		return $aCases;
	}

	/**
	 * @dataProvider SensitiveTypeProvider
	 */
	public function testGetAttributeWithholdsCredentialBearingAttribute(string $sType): void
	{
		[$sClass, $sAttCode] = $this->FindAttributeOfType($sType);

		$oObject = MetaModel::NewObject($sClass);
		$oObject->Set($sAttCode, self::PROBE);

		$oTools = new AIObjectTools();
		$oTools->setContext($oObject);
		$sValue = $oTools->get_attribute($sAttCode);

		static::assertSame('', $sValue,
			"get_attribute() returned a value for $sClass::$sAttCode ($sType). "
			.'Credential-bearing attributes must be withheld from the model.');
	}

	/**
	 * @dataProvider SensitiveTypeProvider
	 */
	public function testGetFilteredAttributeValuesWithholdsCredentialBearingAttribute(string $sType): void
	{
		[$sClass, $sAttCode] = $this->FindAttributeOfType($sType);

		$oObject = MetaModel::NewObject($sClass);
		$oObject->Set($sAttCode, self::PROBE);

		$aValues = AIObjectTools::GetFilteredAttributeValues($oObject, [$sAttCode]);

		static::assertArrayHasKey($sAttCode, $aValues,
			"GetFilteredAttributeValues() dropped $sAttCode entirely instead of "
			.'returning it withheld. The caller cannot tell the difference between '
			.'a withheld attribute and an unknown one.');
		static::assertSame('', $aValues[$sAttCode],
			"GetFilteredAttributeValues() returned a value for $sClass::$sAttCode ($sType).");
	}

	/**
	 * The control: without it, a filter that withholds EVERYTHING would pass every
	 * test above.
	 */
	public function testHarmlessAttributeIsReturnedUnchanged(): void
	{
		$oObject = MetaModel::NewObject('Organization');
		$oObject->Set('name', self::PROBE);

		$oTools = new AIObjectTools();
		$oTools->setContext($oObject);
		static::assertSame(self::PROBE, $oTools->get_attribute('name'),
			'get_attribute() withheld a harmless attribute.');

		$aValues = AIObjectTools::GetFilteredAttributeValues($oObject, ['name']);
		static::assertSame(self::PROBE, $aValues['name'],
			'GetFilteredAttributeValues() withheld a harmless attribute.');
	}

	/**
	 * The bulk reader is meant to be called WITHOUT an attribute list, so that a
	 * consumer no longer has to enumerate attributes itself — that enumeration is
	 * precisely what bypassed the filter in the past. This covers the default.
	 */
	public function testAllAttributesModeStillWithholdsCredentials(): void
	{
		$aFound = $this->FindAnyAttributeOfSensitiveType();
		if ($aFound === null) {
			static::markTestSkipped('No credential-bearing attribute in this datamodel.');
		}
		[$sClass, $sAttCode] = $aFound;

		$oObject = MetaModel::NewObject($sClass);
		$oObject->Set($sAttCode, self::PROBE);

		$aValues = AIObjectTools::GetFilteredAttributeValues($oObject);

		static::assertNotEmpty($aValues, 'GetFilteredAttributeValues() returned nothing at all.');
		static::assertSame('', $aValues[$sAttCode],
			"Reading ALL attributes of $sClass leaked $sAttCode. The default path must "
			.'apply the same filter as the explicit one.');
		static::assertNotContains(self::PROBE, $aValues,
			'The probe value appears in some other attribute of the result.');
	}

	/**
	 * An external field carries the value of an attribute on another class, and its
	 * own type says nothing about it: MailInboxOAuth::client_secret is an
	 * AttributeExternalField whose target is an AttributePassword. Without resolving
	 * the target, the filter waves the secret straight through.
	 *
	 * Skipped where the datamodel has no such attribute — that depends on installed
	 * modules and is not something ai-base can require.
	 */
	public function testExternalFieldPointingAtCredentialIsWithheld(): void
	{
		$aFound = $this->FindExternalFieldToSensitive();
		if ($aFound === null) {
			static::markTestSkipped(
				'No AttributeExternalField with a credential-bearing target in this '
				.'datamodel (needs e.g. combodo-oauth-email-synchro).');
		}
		[$sClass, $sAttCode] = $aFound;

		// External fields are read-only; the value comes from the target object. It
		// is enough that the filter refuses the read — reached before Get().
		$oObject = MetaModel::NewObject($sClass);

		$oTools = new AIObjectTools();
		$oTools->setContext($oObject);
		static::assertSame('', $oTools->get_attribute($sAttCode),
			"get_attribute() returned a value for the external field $sClass::$sAttCode, "
			.'whose target is credential-bearing.');

		$aValues = AIObjectTools::GetFilteredAttributeValues($oObject, [$sAttCode]);
		static::assertSame('', $aValues[$sAttCode],
			"GetFilteredAttributeValues() returned a value for the external field $sClass::$sAttCode.");
	}

	// ---------------------------------------------------------------- Helpers

	/**
	 * First concrete class carrying an attribute of the given type.
	 *
	 * @return array{0: string, 1: string} class and attribute code
	 */
	private function FindAttributeOfType(string $sType): array
	{
		foreach (MetaModel::GetClasses() as $sClass) {
			if (MetaModel::IsAbstract($sClass)) {
				continue;
			}
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				if ($oAttDef instanceof $sType) {
					return [$sClass, $sAttCode];
				}
			}
		}

		static::markTestSkipped("No attribute of type $sType in this datamodel.");
	}

	/** @return array{0: string, 1: string}|null */
	private function FindAnyAttributeOfSensitiveType(): ?array
	{
		foreach (MetaModel::GetClasses() as $sClass) {
			if (MetaModel::IsAbstract($sClass)) {
				continue;
			}
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				foreach (self::SENSITIVE_TYPES as $sType) {
					if ($oAttDef instanceof $sType) {
						return [$sClass, $sAttCode];
					}
				}
			}
		}

		return null;
	}

	/** @return array{0: string, 1: string}|null */
	private function FindExternalFieldToSensitive(): ?array
	{
		foreach (MetaModel::GetClasses() as $sClass) {
			if (MetaModel::IsAbstract($sClass)) {
				continue;
			}
			foreach (MetaModel::ListAttributeDefs($sClass) as $sAttCode => $oAttDef) {
				if (!($oAttDef instanceof AttributeExternalField)) {
					continue;
				}
				try {
					$oTarget = $oAttDef->GetFinalAttDef();
				} catch (\Exception $e) {
					continue;
				}
				foreach (self::SENSITIVE_TYPES as $sType) {
					if ($oTarget instanceof $sType) {
						return [$sClass, $sAttCode];
					}
				}
			}
		}

		return null;
	}
}

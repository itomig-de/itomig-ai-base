<?php
/**
 * Tests for the REST-consistent rendering of non-scalar attribute values
 * (link sets, tag sets) through AIObjectTools.
 *
 * Regression coverage for #85:
 *  - link sets used to come out as '' (ormLinkSet casts to ''), which any
 *    ($value !== '') screening then discards as if it were credential-bearing;
 *  - tag sets used to come out as a ", "-joined string whose boundaries are
 *    ambiguous, and an empty set even cast to a single space.
 *
 * The rendered structures are asserted against the semantics of the core's
 * own REST/JSON export (ObjectResult::MakeResultValue): arrays of per-link
 * entries with the back-reference to the host object skipped, and arrays of
 * tag codes.
 *
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Itomig\iTop\AiBase\Test;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Itomig\iTop\Extension\AIBase\Helper\AIObjectTools;
use MetaModel;
use ormTagSet;

class AIObjectToolsRenderingTest extends ItopDataTestCase
{
	const CREATE_TEST_ORG = true;

	protected function setUp(): void
	{
		parent::setUp();
		$this->RequireOnceItopFile('/env-production/itomig-ai-base/vendor/autoload.php');
	}

	/**
	 * A tag set must reach the model as the same JSON array the core REST/JSON
	 * export produces (AttributeTagSet::GetForJSON), never as a ", "-joined
	 * string. Deliberately includes a tag containing a space, the case that
	 * made the old rendering ambiguous, and an assertion for the empty set,
	 * which used to cast to a single space (#85, Case 3).
	 */
	public function testTagSetRenderingIsRestConsistentJsonArray(): void
	{
		if (!MetaModel::IsValidClass('FAQ')) {
			static::markTestSkipped('FAQ class (itop-faq-light) not present in this datamodel.');
		}

		$this->CreateTagData('FAQ', 'domains', 'network', 'Network');
		$this->CreateTagData('FAQ', 'domains', 'priority high', 'Priority high');
		$oFaq = $this->CreateObjectWithTagSet();

		$oTags = new ormTagSet('FAQ', 'domains');
		$oTags->Add('network');
		$oTags->Add('priority high');
		$oFaq->Set('domains', $oTags);

		$oTools = new AIObjectTools();
		$oTools->setContext($oFaq);
		$sRendered = $oTools->get_attribute('domains');

		static::assertNotSame('', $sRendered,
			'An empty string means "withheld because credential-bearing" — a tag set is never that.');
		$aDecoded = json_decode($sRendered, true);
		static::assertIsArray($aDecoded,
			"get_attribute() did not render the tag set as a JSON array, got: $sRendered");
		static::assertSame(['network', 'priority high'], $aDecoded,
			'The rendered tags must be the tag codes, unsorted-by-rendering, boundaries preserved.');
	}

	/**
	 * A link set must reach the model as the same structure the core REST/JSON
	 * export produces: one entry per link, each holding the link attributes
	 * with the back-reference to the host object skipped. The backbone of
	 * #85 Case 2: the rendering used to be '', indistinguishable from a
	 * withheld credential.
	 */
	public function testLinkSetRenderingIsRestConsistentArrayOfEntries(): void
	{
		if (!MetaModel::IsValidClass('lnkFunctionalCI')) {
			static::markTestSkipped('lnkFunctionalCI class not present in this datamodel.');
		}

		$oServer = $this->CreateServer(85);
		$oTicket = $this->CreateUserRequest(85);

		$oLink = $this->createObject('lnkFunctionalCI', array(
			'functionalci_id' => $oServer->GetKey(),
			'ticket_id' => $oTicket->GetKey(),
		));
		$this->debug('Created '.$oLink->GetKey().' link between server and ticket');
		$this->ReloadObject($oTicket);

		$aFiltered = AIObjectTools::GetFilteredAttributeValues($oTicket, array('functionalcis_list'));
		static::assertArrayHasKey('functionalcis_list', $aFiltered);
		static::assertNotSame('', $aFiltered['functionalcis_list'],
			'A rendered link set must never collide with the "withheld credential" marker (old behaviour, #85 Case 2).');

		$aDecoded = json_decode($aFiltered['functionalcis_list'], true);
		static::assertIsArray($aDecoded);
		static::assertCount(1, $aDecoded);
		static::assertSame($oLink->GetKey(), $aDecoded[0]['id']);
		// Cast to string, as AttributeExternalKey::GetForJSON() does in the
		// REST/JSON export — the representation this rendering must match.
		static::assertSame((string) $oServer->GetKey(), $aDecoded[0]['functionalci_id'],
			'The link entry must carry the link attributes like the REST export does.');
		static::assertArrayNotHasKey('ticket_id', $aDecoded[0],
			'The back-reference to the host object must be skipped, like ObjectResult::MakeResultValue() does.');
	}

	/**
	 * An empty link set renders as a JSON empty array: a legitimate value that
	 * survives screening, distinct both from '' (withheld) and from the old
	 * behaviour, where the field was dropped altogether.
	 */
	public function testEmptyLinkSetRendersAsJsonEmptyArray(): void
	{
		if (!MetaModel::IsValidClass('lnkFunctionalCI')) {
			static::markTestSkipped('lnkFunctionalCI class not present in this datamodel.');
		}

		$oTicket = $this->CreateUserRequest(86);

		$aFiltered = AIObjectTools::GetFilteredAttributeValues($oTicket, array('functionalcis_list'));
		static::assertSame('[]', $aFiltered['functionalcis_list'],
			"An empty link set must render as '[]' so that callers can tell it apart from a withheld value.");
	}
}

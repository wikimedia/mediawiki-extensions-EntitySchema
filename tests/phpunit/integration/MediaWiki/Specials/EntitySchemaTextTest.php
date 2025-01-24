<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use EntitySchema\MediaWiki\Specials\EntitySchemaText;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use HttpError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Title\Title;
use SpecialPageTestBase;

/**
 * @covers \EntitySchema\MediaWiki\Specials\EntitySchemaText
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaTextTest extends SpecialPageTestBase {
	use EntitySchemaIntegrationTestCaseTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testExistingSchema() {
		$testSchema = <<<ShExC
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wd: <http://www.wikidata.org/entity/>

:human {
  wdt:P31 [wd:Q5]
}
ShExC;
		$id = 'E54687';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
		$this->saveSchemaPageContent(
			$this->getServiceContainer()->getWikiPageFactory()->newFromTitle( $title ),
			[ 'schemaText' => $testSchema ]
		);

		/** @var WebResponse $actualWebResponse */
		[ $specialPageResult, $actualWebResponse ] = $this->executeSpecialPage(
			$id,
			new FauxRequest(
				[],
				false
			)
		);

		$this->assertSame( $testSchema, $specialPageResult );

		$this->assertSame(
			'text/shex; charset=UTF-8',
			$actualWebResponse->getHeader( 'Content-Type' )
		);
		$this->assertSame(
			'attachment; filename="' . $id . '.shex"',
			$actualWebResponse->getHeader( 'Content-Disposition' )
		);
	}

	public function testNonExistingSchema() {
		$id = 'E9999999999';
		$this->expectException( HttpError::class );
		$this->executeSpecialPage(
			$id,
			new FauxRequest(
				[],
				false
			)
		);
	}

	protected function newSpecialPage(): EntitySchemaText {
		return new EntitySchemaText();
	}

}

<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Specials;

use CommentStoreComment;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Specials\EntitySchemaText;
use HttpError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebResponse;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use SpecialPageTestBase;
use WikiPage;

/**
 * @covers \EntitySchema\MediaWiki\Specials\EntitySchemaText
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaTextTest extends SpecialPageTestBase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
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
		list( $specialPageResult, $actualWebResponse ) = $this->executeSpecialPage(
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
		$id = 'E999999999999';
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

	private function saveSchemaPageContent( WikiPage $page, array $content ): int {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);

		return $firstRevRecord->getId();
	}

}

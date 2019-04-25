<?php

namespace Wikibase\Schema\Tests\API;

use CommentStoreComment;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use Title;
use Wikibase\Schema\DataAccess\SchemaEncoder;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use WikiPage;

/**
 * @group Database
 * @covers \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContentHandler::getUndoContent
 *
 * @license GPL-2.0-or-later
 */
class UndoAPITest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	public function testGetUndoContentUndoLatest() {
		$handler = new \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContentHandler();

		$id = 'E456';
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id );
		$page = WikiPage::factory( $title );
		$this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => 'en label original ver' ],
		] );
		$rev2 = $this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => 'en label original ver' ],
			'descriptions' => [ 'en' => 'en desc ver 2' ],
		] );
		$rev3 = $this->saveSchemaPageContent( $page, [
			'labels' => [ 'en' => 'en label top version' ],
			'descriptions' => [ 'en' => 'en desc ver 2' ],
		] );

		$actualContent = $handler->getUndoContent(
			$rev3->getContent( SlotRecord::MAIN ),
			$rev3->getContent( SlotRecord::MAIN ),
			$rev2->getContent( SlotRecord::MAIN ),
			true
		);

		$this->assertSame( $actualContent->getText(), $rev2->getContent( SlotRecord::MAIN )->getText() );
	}

	public function testGetUndoContent() {
		$handler = new \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContentHandler();

		$id = 'E456';
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id );
		$page = WikiPage::factory( $title );
		$rev1 = $this->saveSchemaPageContent( $page, [
			'id' => $id,
			'labels' => [ 'en' => 'en label original ver' ],
		] );
		$rev2 = $this->saveSchemaPageContent( $page, [
			'id' => $id,
			'labels' => [ 'en' => 'en label original ver' ],
			'descriptions' => [ 'en' => 'en desc ver 2' ],
		] );
		$rev3 = $this->saveSchemaPageContent( $page, [
			'id' => $id,
			'labels' => [ 'en' => 'en label top version' ],
			'descriptions' => [ 'en' => 'en desc ver 2' ],
		] );

		$actualContent = $handler->getUndoContent(
			$rev3->getContent( SlotRecord::MAIN ),
			$rev2->getContent( SlotRecord::MAIN ),
			$rev1->getContent( SlotRecord::MAIN ),
			false
		);

		$expectedRepresentation = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( $id ),
			[ 'en' => 'en label top version' ],
			[],
			[],
			''
		);
		$this->assertSame( $expectedRepresentation, $actualContent->getText() );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ): RevisionRecord {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);

		return $firstRevRecord;
	}

}

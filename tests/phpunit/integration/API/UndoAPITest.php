<?php

namespace EntitySchema\Tests\Integration\API;

use CommentStoreComment;
use EntitySchema\DataAccess\SchemaEncoder;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use Title;
use WikiPage;

/**
 * @group Database
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler::getUndoContent
 *
 * @license GPL-2.0-or-later
 */
class UndoAPITest extends MediaWikiTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	public function testGetUndoContentUndoLatest() {
		$handler = new EntitySchemaContentHandler();

		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
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
		$handler = new EntitySchemaContentHandler();

		$id = 'E456';
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id );
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
		$updater->setContent( SlotRecord::MAIN, new EntitySchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);

		return $firstRevRecord;
	}

}

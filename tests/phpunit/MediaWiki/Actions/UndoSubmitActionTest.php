<?php

namespace Wikibase\Schema\Tests\MediaWiki\Actions;

use Block;
use CommentStoreComment;
use FauxRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use PermissionsError;
use RequestContext;
use Title;
use UserBlockedError;
use Wikibase\Schema\MediaWiki\Actions\UndoSubmitAction;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \Wikibase\Schema\MediaWiki\Actions\UndoSubmitAction
 * @covers \Wikibase\Schema\MediaWiki\Actions\AbstractUndoAction
 * @covers \Wikibase\Schema\MediaWiki\UndoHandler
 */
class UndoSubmitActionTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	/** @var Block */
	private $block;

	protected function tearDown() {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	public function testUndoSubmit() {
		$schemaId = 'E123';
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc', 'id' => $schemaId ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def', 'id' => $schemaId ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
				'title' => 'Schema:' . $schemaId
			], true )
		);

		$undoSubmitAction = new UndoSubmitAction( $page, $context );

		$undoSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( $schemaId );
		$this->assertSame( 'abc', $actualSchema['schemaText'] );
	}

	public function testUndoSubmitNoPOST() {
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
			], false )
		);

		$undoSubmitAction = new UndoSubmitAction( $page, $context );

		$undoSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'E123' );
		$this->assertSame( 'def', $actualSchema['schemaText'] );
	}

	public function testUndoSubmitBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new Block(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser->getId(),
			]
		);
		$this->block->insert();

		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
			], true )
		);
		$context->setUser( $testuser );

		$undoSubmitAction = new UndoSubmitAction( $page, $context );

		$this->expectException( UserBlockedError::class );

		$undoSubmitAction->show();
	}

	public function testUndoSubmitNoPermissions() {
		global $wgGroupPermissions;

		$groupPermissions = $wgGroupPermissions;
		$groupPermissions['*']['edit'] = false;
		$this->setMwGlobals( 'wgGroupPermissions', $groupPermissions );

		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
			], true )
		);

		$undoSubmitAction = new UndoSubmitAction( $page, $context );

		$this->expectException( PermissionsError::class );

		$undoSubmitAction->show();
	}

	private function getCurrentSchemaContent( $pageName ) {
		/** @var WikibaseSchemaContent $content */
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
		$content['serializationVersion'] = '3.0';
		$updater = $page->newPageUpdater( self::getTestUser()->getUser() );
		$updater->setContent( SlotRecord::MAIN, new WikibaseSchemaContent( json_encode( $content ) ) );
		$firstRevRecord = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment(
				'test summary 1'
			)
		);

		return $firstRevRecord->getId();
	}

}

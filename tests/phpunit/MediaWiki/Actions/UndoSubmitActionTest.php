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
 * @covers \Wikibase\Schema\MediaWiki\Actions\UndoSubmitAction
 * @covers \Wikibase\Schema\MediaWiki\Actions\AbstractUndoAction
 */
class UndoSubmitActionTest extends MediaWikiTestCase {

	/** @var Block */
	private $block;

	protected function tearDown() {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	public function testUndoSubmit() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
			], true )
		);

		$undoSubmitAction = new UndoSubmitAction( $page, $context );

		$undoSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'O123' );
		$this->assertSame( 'abc', $actualSchema['schema'] );
	}

	public function testUndoSubmitNoPOST() {
		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

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

		$actualSchema = $this->getCurrentSchemaContent( 'O123' );
		$this->assertSame( 'def', $actualSchema['schema'] );
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

		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

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

		$page = WikiPage::factory( Title::makeTitle( NS_WBSCHEMA_JSON, 'O123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schema' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schema' => 'def' ] );

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
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
		$content['serializationVersion'] = '2.0';
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

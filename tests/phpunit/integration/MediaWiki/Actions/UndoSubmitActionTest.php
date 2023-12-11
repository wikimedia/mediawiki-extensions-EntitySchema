<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use RequestContext;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\MediaWiki\Actions\UndoSubmitAction
 * @covers \EntitySchema\MediaWiki\Actions\AbstractUndoAction
 * @covers \EntitySchema\MediaWiki\UndoHandler
 */
class UndoSubmitActionTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	private DatabaseBlock $block;

	protected function tearDown(): void {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	public function testUndoSubmit() {
		$schemaId = 'E123';
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc', 'id' => $schemaId ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def', 'id' => $schemaId ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'undoafter' => $firstID,
				'undo' => $secondId,
				'title' => 'Schema:' . $schemaId,
			], true )
		);

		$undoSubmitAction = new UndoSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$undoSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( $schemaId );
		$this->assertSame( 'abc', $actualSchema['schemaText'] );
	}

	public function testUndoSubmitNoPOST() {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

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

		$undoSubmitAction = new UndoSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$undoSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'E123' );
		$this->assertSame( 'def', $actualSchema['schemaText'] );
	}

	public function testUndoSubmitBlocked() {
		$testuser = self::getTestUser()->getUser();
		$this->block = new DatabaseBlock(
			[
				'address' => $testuser,
				'reason' => 'testing in ' . __CLASS__,
				'by' => $testuser,
			]
		);
		$this->block->insert();

		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

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

		$undoSubmitAction = new UndoSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$this->expectException( PermissionsError::class );

		$undoSubmitAction->show();
	}

	public function testUndoSubmitNoPermissions() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions',
			[ '*' => [ 'edit' => false ] ] );

		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

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

		$undoSubmitAction = new UndoSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$this->expectException( PermissionsError::class );

		$undoSubmitAction->show();
	}

	private function getCurrentSchemaContent( string $pageName ): array {
		/** @var EntitySchemaContent $content */
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
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

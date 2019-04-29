<?php

namespace EntitySchema\Tests\MediaWiki\Actions;

use Action;
use Block;
use CommentStoreComment;
use FauxRequest;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWikiTestCase;
use RequestContext;
use Title;
use UserBlockedError;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\MediaWiki\Actions\RestoreSubmitAction
 */
final class RestoreSubmitActionTest extends MediaWikiTestCase {

	/** @var Block */
	private $block;

	public function setUp() {
		parent::setUp();
		$this->tablesUsed[] = 'page';
		$this->tablesUsed[] = 'revision';
		$this->tablesUsed[] = 'recentchanges';
	}

	protected function tearDown() {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	public function testRestoreSubmit() {
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$restoreSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'E123' );
		$this->assertSame( 'abc', $actualSchema['schemaText'] );
	}

	public function testRestoreNotCurrent() {
		$page = WikiPage::factory( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] );
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );
		$this->saveSchemaPageContent( $page, [ 'schemaText' => 'ghi' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$restoreSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'E123' );
		$this->assertSame(
			'ghi',
			$actualSchema['schemaText'],
			'The restore must fail if wpBaseRev is not the current revision!'
		);
	}

	public function testRestoreSubmitBlocked() {
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
		$this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
			], true )
		);
		$context->setUser( $testuser );

		$restoreSubmitAction = new RestoreSubmitAction( $page, $context );

		$this->expectException( UserBlockedError::class );

		$restoreSubmitAction->show();
	}

	private function getCurrentSchemaContent( $pageName ) {
		/** @var EntitySchemaContent $content */
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $pageName );
		$rev = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $title->getLatestRevID() );
		return json_decode( $rev->getContent( SlotRecord::MAIN )->getText(), true );
	}

	private function saveSchemaPageContent( WikiPage $page, array $content ) {
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

	public function testActionName() {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$requestParameters = [ 'action' => 'submit', 'restore' => 1 ];
		$context = RequestContext::newExtraneousContext( $title, $requestParameters );

		$actionName = Action::getActionName( $context );
		$action = Action::factory( $actionName, $context->getWikiPage(), $context );

		$this->assertInstanceOf( RestoreSubmitAction::class, $action );
	}

}

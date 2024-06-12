<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PermissionsError;
use RequestContext;

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
	use EntitySchemaIntegrationTestCaseTrait;

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

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc', 'id' => $schemaId ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def', 'id' => $schemaId ] )->getId();

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

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] )->getId();

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

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] )->getId();

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

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] )->getId();

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

}

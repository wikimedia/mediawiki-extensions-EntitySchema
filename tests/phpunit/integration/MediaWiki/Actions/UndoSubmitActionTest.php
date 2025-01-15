<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Article;
use EntitySchema\MediaWiki\Actions\UndoSubmitAction;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PermissionsError;
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
	use EntitySchemaIntegrationTestCaseTrait;
	use TempUserTestTrait;

	private DatabaseBlock $block;

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	protected function tearDown(): void {
		if ( isset( $this->block ) ) {
			$this->block->delete();
		}

		parent::tearDown();
	}

	private function getUndoSubmitAction( WikiPage $page, IContextSource $context ) {
		$services = $this->getServiceContainer();
		return new UndoSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context,
			$services->getReadOnlyMode(),
			$services->getPermissionManager(),
			$services->getRevisionStore()
		);
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

		$undoSubmitAction = $this->getUndoSubmitAction( $page, $context );

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

		$undoSubmitAction = $this->getUndoSubmitAction( $page, $context );

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
		$this->getServiceContainer()->getDatabaseBlockStore()
			->insertBlock( $this->block );

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

		$undoSubmitAction = $this->getUndoSubmitAction( $page, $context );

		$this->expectException( PermissionsError::class );

		$undoSubmitAction->show();
	}

	public function testUndoSubmitNoPermissions() {
		$this->setGroupPermissions( [ '*' => [ 'edit' => false ] ] );

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

		$undoSubmitAction = $this->getUndoSubmitAction( $page, $context );

		$this->expectException( PermissionsError::class );

		$undoSubmitAction->show();
	}

	public function testUndoSubmitCreateTempUser(): void {
		$this->enableAutoCreateTempUser();
		$this->addTempUserHook();
		$schemaId = 'E123';
		$services = $this->getServiceContainer();
		$title = $services->getTitleFactory()
			->makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId );
		$page = $services->getWikiPageFactory()
			->newFromTitle( $title );

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
		$context->setUser( $services->getUserFactory()->newAnonymous() );

		$undoSubmitAction = $this->getUndoSubmitAction( $page, $context );

		$undoSubmitAction->show();

		$revision = $services->getRevisionLookup()
			->getRevisionByTitle( $title );
		$user = $revision->getUser();
		$this->assertTrue( $services->getUserIdentityUtils()->isTemp( $user ) );
		$redirect = $undoSubmitAction->getOutput()->getRedirect();
		$this->assertRedirectToEntitySchema( $title, $redirect );
	}

}

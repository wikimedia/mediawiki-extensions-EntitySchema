<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Actions;

use Action;
use Article;
use EntitySchema\MediaWiki\Actions\RestoreSubmitAction;
use EntitySchema\Tests\Integration\EntitySchemaIntegrationTestCaseTrait;
use ExtensionRegistry;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use PermissionsError;

/**
 * @license GPL-2.0-or-later
 *
 * @group Database
 *
 * @covers \EntitySchema\MediaWiki\Actions\RestoreSubmitAction
 */
final class RestoreSubmitActionTest extends MediaWikiIntegrationTestCase {
	use EntitySchemaIntegrationTestCaseTrait;

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

	public function testRestoreSubmit() {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] )->getId();

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId,
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$restoreSubmitAction->show();

		$actualSchema = $this->getCurrentSchemaContent( 'E123' );
		$this->assertSame( 'abc', $actualSchema['schemaText'] );
	}

	public function testRestoreNotCurrent() {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E123' ) );

		$firstID = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'abc' ] )->getId();
		$secondId = $this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] )->getId();
		$this->saveSchemaPageContent( $page, [ 'schemaText' => 'ghi' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
				'wpBaseRev' => $secondId,
			], true )
		);

		$restoreSubmitAction = new RestoreSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

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
		$this->saveSchemaPageContent( $page, [ 'schemaText' => 'def' ] );

		$context = RequestContext::getMain();
		$context->setWikiPage( $page );
		$context->setRequest( new FauxRequest( [
				'action' => 'submit',
				'restore' => $firstID,
			], true )
		);
		$context->setUser( $testuser );

		$restoreSubmitAction = new RestoreSubmitAction(
			Article::newFromWikiPage( $page, $context ),
			$context
		);

		$this->expectException( PermissionsError::class );

		$restoreSubmitAction->show();
	}

	public function testActionName() {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$requestParameters = [ 'action' => 'submit', 'restore' => 1 ];
		$context = RequestContext::newExtraneousContext( $title, $requestParameters );

		$actionName = Action::getActionName( $context );
		$action = Action::factory(
			$actionName,
			Article::newFromTitle( $title, $context ),
			$context
		);

		$this->assertInstanceOf( RestoreSubmitAction::class, $action );
	}

}

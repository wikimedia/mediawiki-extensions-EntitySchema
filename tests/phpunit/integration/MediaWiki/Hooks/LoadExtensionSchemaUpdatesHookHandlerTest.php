<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use DatabaseUpdater;
use EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler
 *
 * @group Database
 */
class LoadExtensionSchemaUpdatesHookHandlerTest extends MediaWikiIntegrationTestCase {

	public function testOnLoadExtensionSchemaUpdates() {
		$this->overrideConfigValue( 'EntitySchemaIsRepo', true );
		$db = $this->createMock( IDatabase::class );
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createMock( DatabaseUpdater::class );

		$updater->expects( $this->any() )->method( 'getDB' )->willReturn( $db );
		$updater->expects( $this->once() )->method( 'addExtensionTable' );
		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}

	public function testOnLoadExtensionSchemaUpdates_repoDisabled(): void {
		$this->overrideConfigValue( 'EntitySchemaIsRepo', false );
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createNoOpMock( DatabaseUpdater::class );

		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}
}

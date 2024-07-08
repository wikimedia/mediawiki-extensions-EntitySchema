<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler
 *
 * @group Database
 * @group EntitySchemaClient
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

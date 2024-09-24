<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Registration\ExtensionRegistry;
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

	protected function markTestSkippedIfExtensionLoaded( string $extensionName ): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( $extensionName ) ) {
			$this->markTestSkipped( "This test requires extension $extensionName to not be loaded" );
		}
	}

	public function testOnLoadExtensionSchemaUpdates_repoInstalledAndEnabled() {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->overrideConfigValue( 'EntitySchemaIsRepo', true );
		$db = $this->createMock( IDatabase::class );
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createMock( DatabaseUpdater::class );

		$updater->expects( $this->any() )->method( 'getDB' )->willReturn( $db );
		$updater->expects( $this->once() )->method( 'addExtensionTable' );
		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}

	public function testOnLoadExtensionSchemaUpdates_repoNotInstalled() {
		$this->markTestSkippedIfExtensionLoaded( 'WikibaseRepository' );
		$this->overrideConfigValue( 'EntitySchemaIsRepo', true ); // has no effect if WikibaseRepository not loaded
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createNoOpMock( DatabaseUpdater::class );

		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}

	public function testOnLoadExtensionSchemaUpdates_repoDisabled(): void {
		$this->overrideConfigValue( 'EntitySchemaIsRepo', false );
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createNoOpMock( DatabaseUpdater::class );

		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}
}

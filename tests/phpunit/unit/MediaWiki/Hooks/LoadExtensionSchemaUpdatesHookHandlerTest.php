<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use DatabaseUpdater;
use EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\LoadExtensionSchemaUpdatesHookHandler
 *
 * @group Database
 */
class LoadExtensionSchemaUpdatesHookHandlerTest extends MediaWikiUnitTestCase {

	public function testOnLoadExtensionSchemaUpdates() {
		$db = $this->createMock( IDatabase::class );
		$hookHandler = new LoadExtensionSchemaUpdatesHookHandler();
		$updater = $this->createMock( DatabaseUpdater::class );

		$updater->expects( $this->any() )->method( 'getDB' )->willReturn( $db );
		$updater->expects( $this->once() )->method( 'addExtensionTable' );
		$hookHandler->onLoadExtensionSchemaUpdates( $updater );
	}
}

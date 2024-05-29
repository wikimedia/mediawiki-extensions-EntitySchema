<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\ResourceLoaderRegisterModulesHookHandler;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\ResourceLoaderRegisterModulesHookHandler
 * @license GPL-2.0-or-later
 */
class ResourceLoaderRegisterModulesHookHandlerTest extends MediaWikiUnitTestCase {

	public function testOnResourceLoaderRegisterModules() {
		$handler = new ResourceLoaderRegisterModulesHookHandler( true );

		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->expects( $this->once() )
			->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertCount( 6, $rlModules );
		$this->assertArrayHasKey( 'ext.EntitySchema.experts.EntitySchema', $rlModules );
	}

	public function testOnResourceLoaderRegisterModules_client() {
		$handler = new ResourceLoaderRegisterModulesHookHandler( false );

		$rl = $this->createMock( ResourceLoader::class );
		$rl->expects( $this->never() )
			->method( 'register' );

		$handler->onResourceLoaderRegisterModules( $rl );
	}

}

<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\GetContentModelsHookHandler;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\GetContentModelsHookHandler
 * @license GPL-2.0-or-later
 */
class GetContentModelsHookHandlerTest extends MediaWikiUnitTestCase {

	public function testOnGetContentModels() {
		$handler = new GetContentModelsHookHandler( true );

		$list = [ 'a' ];
		$handler->onGetContentModels( $list );

		$this->assertSame( [ 'a', 'EntitySchema' ], $list );
	}

	public function testOnGetContentModels_client() {
		$handler = new GetContentModelsHookHandler( false );

		$list = [ 'a' ];
		$handler->onGetContentModels( $list );

		$this->assertSame( [ 'a' ], $list );
	}

}

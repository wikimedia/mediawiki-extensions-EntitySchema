<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\SpecialPageInitListHookHandler;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\SpecialPageInitListHookHandler
 * @license GPL-2.0-or-later
 */
class SpecialPageInitListHookHandlerTest extends MediaWikiUnitTestCase {

	public function testOnSpecialPage_initList() {
		$handler = new SpecialPageInitListHookHandler( true );

		$list = [ 'a' => 'untouched' ];
		$handler->onSpecialPage_initList( $list );

		$this->assertCount( 4, $list );
		$this->assertSame( 'untouched', $list['a'] );
		$this->assertArrayHasKey( 'NewEntitySchema', $list );
		$this->assertArrayHasKey( 'EntitySchemaText', $list );
		$this->assertArrayHasKey( 'SetEntitySchemaLabelDescriptionAliases', $list );
	}

	public function testOnSpecialPage_initList_client() {
		$handler = new SpecialPageInitListHookHandler( false );

		$originalList = $list = [ 'a' => 'untouched' ];
		$handler->onSpecialPage_initList( $list );

		$this->assertSame( $originalList, $list );
	}

}

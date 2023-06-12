<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\SidebarHookHandler;
use MediaWikiUnitTestCase;
use Message;
use Skin;
use Title;
use Wikibase\DataAccess\EntitySource;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\SidebarHookHandler
 * @license GPL-2.0-or-later
 */
class SidebarHandlerTest extends MediaWikiUnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public function testBuildConceptUriLinkReturnsLink() {

		$conceptBaseUri = 'www.test.com/';

		$skin = $this->createMock( Skin::class );
		$title = $this->createMock( Title::class );
		$title->method( 'inNamespace' )->willReturn( true );
		$title->method( 'getText' )->willReturn( 'E1' );

		$skin->method( 'getTitle' )->willReturn( $title );
		$skin->method( 'msg' )->willReturn( $this->createMock( Message::class ) );

		$localEntitySource = $this->createMock( EntitySource::class );
		$localEntitySource->method( 'getConceptBaseUri' )->willReturn( $conceptBaseUri );

		$handler = new SidebarHookHandler( $localEntitySource );
		$resultArray = $handler->buildConceptUriLink( $skin );

		$this->assertEquals( 'www.test.com/E1', $resultArray['href'] );
	}

	public function test_buildConceptUriLink_WithNoTitleReturnsNull() {

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getTitle' )->willReturn( null );

		$localEntitySource = $this->createMock( EntitySource::class );
		$handler = new SidebarHookHandler( $localEntitySource );
		$resultArray = $handler->buildConceptUriLink( $skin );

		$this->assertNull( $resultArray );
	}

	public function test_buildConceptUriLink_TitleNotEntitySchemaReturnsNull() {

		$skin = $this->createMock( Skin::class );
		$title = $this->createMock( Title::class );
		$title->method( 'inNamespace' )->willReturn( false );
		$title->method( 'getText' )->willReturn( 'E1' );

		$skin->method( 'getTitle' )->willReturn( $title );

		$localEntitySource = $this->createMock( EntitySource::class );
		$handler = new SidebarHookHandler( $localEntitySource );
		$resultArray = $handler->buildConceptUriLink( $skin );
		$this->assertNull( $resultArray );
	}

}

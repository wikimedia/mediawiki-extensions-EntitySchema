<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\SidebarBeforeOutputHookHandler;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use Skin;
use Wikibase\DataAccess\EntitySource;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\SidebarBeforeOutputHookHandler
 * @license GPL-2.0-or-later
 */
class SidebarBeforeOutputHookHandlerTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

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

		$handler = new SidebarBeforeOutputHookHandler( true, $localEntitySource );
		$resultArray = $handler->buildConceptUriLink( $skin );

		$this->assertEquals( 'www.test.com/E1', $resultArray['href'] );
	}

	public function test_buildConceptUriLink_WithNoTitleReturnsNull() {

		$skin = $this->createMock( Skin::class );
		$skin->method( 'getTitle' )->willReturn( null );

		$localEntitySource = $this->createMock( EntitySource::class );
		$handler = new SidebarBeforeOutputHookHandler( true, $localEntitySource );
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
		$handler = new SidebarBeforeOutputHookHandler( true, $localEntitySource );
		$resultArray = $handler->buildConceptUriLink( $skin );
		$this->assertNull( $resultArray );
	}

}

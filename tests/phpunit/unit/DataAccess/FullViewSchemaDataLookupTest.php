<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use JsonContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiUnitTestCase;
use TitleFactory;
use WikiPage;

/**
 * @covers \EntitySchema\DataAccess\FullViewSchemaDataLookup
 * @license GPL-2.0-or-later
 */
class FullViewSchemaDataLookupTest extends MediaWikiUnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public function testGetFullViewSchemaData_invalidTitle(): void {
		$id = $this->createConfiguredMock( EntitySchemaId::class,
			[ 'getId' => '<' ] );
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitleSafe' )
			->with( NS_ENTITYSCHEMA_JSON, '<' )
			->willReturn( null );
		$lookup = new FullViewSchemaDataLookup(
			$titleFactory,
			$this->createNoOpMock( WikiPageFactory::class )
		);

		$this->assertNull( $lookup->getFullViewSchemaData( $id ) );
	}

	public function testGetFullViewSchemaDataForTitle_invalidContent(): void {
		$content = new JsonContent( '{}' );
		$wikiPage = $this->createConfiguredMock( WikiPage::class,
			[ 'getContent' => $content ] );
		$wikiPageLookup = $this->createConfiguredMock( WikiPageFactory::class,
			[ 'newFromTitle' => $wikiPage ] );
		$lookup = new FullViewSchemaDataLookup(
			$this->createNoOpMock( TitleFactory::class ),
			$wikiPageLookup
		);

		$this->assertNull( $lookup->getFullViewSchemaDataForTitle( $wikiPage ) );
	}

	// note: “successful” tests are found in the integration test

}

<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use JsonContent;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use WikiPage;

/**
 * @covers \EntitySchema\DataAccess\FullViewSchemaDataLookup
 * @license GPL-2.0-or-later
 */
class FullViewSchemaDataLookupTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

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

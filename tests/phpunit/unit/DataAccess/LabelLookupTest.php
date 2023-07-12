<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiUnitTestCase;
use WikiPage;

/**
 * @covers \EntitySchema\DataAccess\LabelLookup
 * @license GPL-2.0-or-later
 */
class LabelLookupTest extends MediaWikiUnitTestCase {

	public function testTitleDoesNotExist(): void {
		$stubWikiPage = $this->createConfiguredMock(
			WikiPage::class,
			[ 'getContent' => null ]
		);
		$stubWikiPageFactory = $this->createConfiguredMock(
			WikiPageFactory::class,
			[ 'newFromTitle' => $stubWikiPage ]
		);
		$labelLookup = new LabelLookup( $stubWikiPageFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testNoLabelInLanguage(): void {
		$stubWikiPage = $this->createConfiguredMock(
			WikiPage::class,
			[ 'getContent' => $this->getNewEntitySchemaContent( [
				'en' => 'Human',
			] ) ]
		);
		$stubWikiPageFactory = $this->createConfiguredMock(
			WikiPageFactory::class,
			[ 'newFromTitle' => $stubWikiPage ]
		);

		$labelLookup = new LabelLookup( $stubWikiPageFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de' );

		$this->assertNull( $actualResult );
	}

	public function testLabelInLanguageAvailable(): void {
		$stubWikiPage = $this->createConfiguredMock(
			WikiPage::class,
			[ 'getContent' => $this->getNewEntitySchemaContent( [
				'de' => 'Mensch',
			] ) ]
		);
		$stubWikiPageFactory = $this->createConfiguredMock(
			WikiPageFactory::class,
			[ 'newFromTitle' => $stubWikiPage ]
		);

		$labelLookup = new LabelLookup( $stubWikiPageFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de' );

		$this->assertSame( 'de', $actualResult->getLanguageCode() );
		$this->assertSame( 'Mensch', $actualResult->getText() );
	}

	private function getNewEntitySchemaContent( array $labels ): EntitySchemaContent {
		return new EntitySchemaContent(
			json_encode(
				[
					'id' => 'E123',
					'serializationVersion' => '3.0',
					'labels' => $labels,
					'descriptions' => [],
					'aliases' => [],
					'schemaText' => '',
					'type' => 'ShExC',
				]
			)
		);
	}
}

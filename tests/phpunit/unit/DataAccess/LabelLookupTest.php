<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWikiUnitTestCase;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;
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
		$stubLanguageFallbackChainFactory = $this->createStub( LanguageFallbackChainFactory::class );
		$labelLookup = new LabelLookup( $stubWikiPageFactory, $stubLanguageFallbackChainFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testNoLabelInLanguage(): void {
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
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => null ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$labelLookup = new LabelLookup( $stubWikiPageFactory, $stubLanguageFallbackChainFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'en' );

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
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => [
				'value' => 'Mensch',
				'language' => 'de',
				'source' => 'de',
			] ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$labelLookup = new LabelLookup( $stubWikiPageFactory, $stubLanguageFallbackChainFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de' );

		$this->assertSame( 'de', $actualResult->getLanguageCode() );
		$this->assertSame( 'de', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'Mensch', $actualResult->getText() );
	}

	public function testLabelInFallbackLanguageAvailable(): void {
		$stubWikiPage = $this->createConfiguredMock(
			WikiPage::class,
			[ 'getContent' => $this->getNewEntitySchemaContent( [
				'en' => 'human',
			] ) ]
		);
		$stubWikiPageFactory = $this->createConfiguredMock(
			WikiPageFactory::class,
			[ 'newFromTitle' => $stubWikiPage ]
		);
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => [
				'value' => 'human',
				'language' => 'en',
				'source' => 'en',
			] ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);

		$labelLookup = new LabelLookup( $stubWikiPageFactory, $stubLanguageFallbackChainFactory );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de-at' );

		$this->assertSame( 'de-at', $actualResult->getLanguageCode() );
		$this->assertSame( 'en', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'human', $actualResult->getText() );
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

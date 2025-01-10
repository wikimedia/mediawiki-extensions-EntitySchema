<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\DataAccess;

use EntitySchema\DataAccess\FullViewSchemaDataLookup;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\DataAccess\SchemaDataResolvingLabelLookup;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use MediaWiki\Page\PageIdentity;
use MediaWikiUnitTestCase;
use Wikibase\Lib\LanguageFallbackChainFactory;
use Wikibase\Lib\TermLanguageFallbackChain;

/**
 * @covers \EntitySchema\DataAccess\LabelLookup
 * @license GPL-2.0-or-later
 */
class SchemaDataResolvingLabelLookupTest extends MediaWikiUnitTestCase {

	public function testTitleDoesNotExist(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => null ]
		);
		$labelLookup = new SchemaDataResolvingLabelLookup(
			$this->createMock( FullViewSchemaDataLookup::class ),
			$this->createMock( LabelLookup::class )
		);

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testNoLabelInLanguage(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'de' => new NameBadge( 'Mensch', '', [] ),
			], '' ) ]
		);
		$stubLanguageFallbackChain = $this->createConfiguredMock(
			TermLanguageFallbackChain::class,
			[ 'extractPreferredValue' => null ],
		);
		$stubLanguageFallbackChainFactory = $this->createConfiguredMock(
			LanguageFallbackChainFactory::class,
			[ 'newFromLanguageCode' => $stubLanguageFallbackChain ]
		);
		$wrappedLabelLookup = new LabelLookup( $stubLanguageFallbackChainFactory );

		$labelLookup = new SchemaDataResolvingLabelLookup( $stubDataLookup, $wrappedLabelLookup );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'en' );

		$this->assertNull( $actualResult );
	}

	public function testLabelInLanguageAvailable(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'de' => new NameBadge( 'Mensch', '', [] ),
			], '' ) ]
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

		$wrappedLabelLookup = new LabelLookup( $stubLanguageFallbackChainFactory );
		$labelLookup = new SchemaDataResolvingLabelLookup( $stubDataLookup, $wrappedLabelLookup );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de' );

		$this->assertSame( 'de', $actualResult->getLanguageCode() );
		$this->assertSame( 'de', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'Mensch', $actualResult->getText() );
	}

	public function testLabelInFallbackLanguageAvailable(): void {
		$stubDataLookup = $this->createConfiguredMock(
			FullViewSchemaDataLookup::class,
			[ 'getFullViewSchemaDataForTitle' => new FullViewEntitySchemaData( [
				'en' => new NameBadge( 'human', '', [] ),
			], '' ) ]
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

		$wrappedLabelLookup = new LabelLookup( $stubLanguageFallbackChainFactory );
		$labelLookup = new SchemaDataResolvingLabelLookup( $stubDataLookup, $wrappedLabelLookup );

		$actualResult = $labelLookup->getLabelForTitle( $this->createMock( PageIdentity::class ), 'de-at' );

		$this->assertSame( 'de-at', $actualResult->getLanguageCode() );
		$this->assertSame( 'en', $actualResult->getActualLanguageCode() );
		$this->assertSame( 'human', $actualResult->getText() );
	}
}
